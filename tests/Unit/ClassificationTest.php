<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\Classification\ClassificationPolicy;
use Lzk\DuplicateDetector\Classification\ClassificationRule;
use Lzk\DuplicateDetector\Classification\ContradictionRequirement;
use Lzk\DuplicateDetector\Classification\DeterministicRiskClassifier;
use Lzk\DuplicateDetector\Classification\DuplicateMatchClassifier;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use Lzk\DuplicateDetector\DTO\FieldComparison;
use Lzk\DuplicateDetector\DTO\FieldOutcome;
use Lzk\DuplicateDetector\Exact\ExactComparator;
use Lzk\DuplicateDetector\Exact\ExactRule;
use Lzk\DuplicateDetector\Fuzzy\ContradictionRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyComparator;
use Lzk\DuplicateDetector\Fuzzy\FuzzyFieldRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyRule;
use Lzk\DuplicateDetector\Metrics\NormalizedLevenshtein;
use Lzk\DuplicateDetector\Normalizers\EmailNormalizer;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClassificationTest extends TestCase
{
    private function classifier(): DeterministicRiskClassifier
    {
        return new DeterministicRiskClassifier(new ClassificationPolicy(
            id: 'contact-review.v1',
            rules: [
                new ClassificationRule(
                    id: 'contradiction-review.v1',
                    label: 'manual-review',
                    contradictions: ContradictionRequirement::Present,
                ),
                new ClassificationRule(
                    id: 'exact-email.v1',
                    label: 'exact-candidate',
                    comparisonRuleIds: ['contact.exact.v1'],
                    matchedFields: ['email'],
                    contradictions: ContradictionRequirement::None,
                    minimumEvidence: 1,
                ),
                new ClassificationRule(
                    id: 'fuzzy-name-high.v1',
                    label: 'similar-candidate',
                    comparisonRuleIds: ['contact.fuzzy.v1'],
                    minimumSimilarities: ['name' => 0.85],
                    contradictions: ContradictionRequirement::None,
                    minimumEvidence: 2,
                    requiredEvidenceCodes: ['similarity.name.v1.match'],
                    presentFields: ['name'],
                ),
                new ClassificationRule(
                    id: 'missing-name.v1',
                    label: 'insufficient-data',
                    missingFields: ['name'],
                ),
            ],
            defaultLabel: 'not-classified',
        ));
    }

    public function testExactComparisonCanBeClassifiedIndependently(): void
    {
        $rule = new ExactRule(
            'contact.exact.v1',
            ['email' => new EmailNormalizer()],
            ['email'],
            ['email' => 'email'],
        );
        $comparison = (new ExactComparator($rule))->compare(
            ['email' => 'person@example.test'],
            ['email' => 'person@example.test'],
        );

        $classification = $this->classifier()->classify($comparison);
        self::assertSame('exact-candidate', $classification->label);
        self::assertSame('contact-review.v1', $classification->policyId);
        self::assertSame('exact-email.v1', $classification->ruleId);
        self::assertContains('field.email.matched', $classification->reasons);
        self::assertContains('field.email.match', $classification->evidence);
    }

    public function testMatchedFieldMetadataCanDriveAClassification(): void
    {
        $classifier = new DeterministicRiskClassifier(new ClassificationPolicy(
            'matched-fields.v1',
            [new ClassificationRule('email.v1', 'matched', matchedFields: ['email'])],
            'fallback',
        ));
        $classification = $classifier->classify(new ComparisonResult(matchedFields: ['email']));

        self::assertSame('matched', $classification->label);
        self::assertSame('email.v1', $classification->ruleId);
    }

    public function testHighFuzzySimilarityAndMultipleEvidenceMatchConfiguredRule(): void
    {
        $comparison = $this->fuzzyComparison('jose alvarez', 'jose alvares', 'doc-a', 'doc-a');
        $classification = $this->classifier()->classify($comparison);

        self::assertSame('similar-candidate', $classification->label);
        self::assertSame('fuzzy-name-high.v1', $classification->ruleId);
        self::assertContains('field.name.similarity-minimum', $classification->reasons);
        self::assertContains('evidence.minimum', $classification->reasons);
        self::assertGreaterThanOrEqual(2, count($classification->evidence));
    }

    public function testLowFuzzySimilarityFallsBackWithoutInventingAClassification(): void
    {
        $classification = $this->classifier()->classify(
            $this->fuzzyComparison('jose alvarez', 'unrelated value', 'doc-a', 'doc-a'),
        );

        self::assertSame('not-classified', $classification->label);
        self::assertNull($classification->ruleId);
        self::assertSame(['classification.default'], $classification->reasons);
    }

    public function testContradictionHasDeclaredPriorityOverHighSimilarity(): void
    {
        $classification = $this->classifier()->classify(
            $this->fuzzyComparison('jose alvarez', 'jose alvarez', 'doc-a', 'doc-b'),
        );

        self::assertSame('manual-review', $classification->label);
        self::assertSame('contradiction-review.v1', $classification->ruleId);
        self::assertContains('contradictions.present', $classification->reasons);
        self::assertContains('contradiction.identity.v1', $classification->evidence);
    }

    public function testMissingFieldCanBeHandledByAnExplicitRule(): void
    {
        $classification = $this->classifier()->classify(new ComparisonResult(
            evidence: ['similarity.name.v1.missing'],
            ruleId: 'contact.fuzzy.v1',
            fieldOutcomes: ['name' => FieldOutcome::Missing],
        ));

        self::assertSame('insufficient-data', $classification->label);
        self::assertContains('field.name.missing', $classification->reasons);
    }

    public function testOverlappingRulesUseDeclarationOrderDeterministically(): void
    {
        $comparison = new ComparisonResult(evidence: ['safe.code']);
        $policy = new ClassificationPolicy('ordered.v1', [
            new ClassificationRule('first.v1', 'first'),
            new ClassificationRule('second.v1', 'second'),
        ], 'fallback');
        $classifier = new DeterministicRiskClassifier($policy);

        self::assertSame('first.v1', $classifier->classify($comparison)->ruleId);
        self::assertEquals(
            $classifier->classify($comparison),
            $classifier->classify($comparison),
        );
    }

    public function testEmptyPolicyUsesExplicitDefault(): void
    {
        $classifier = new DeterministicRiskClassifier(new ClassificationPolicy(
            'empty.v1',
            [],
            'consumer-default',
        ));
        self::assertSame('consumer-default', $classifier->classify(new ComparisonResult())->label);
    }

    public function testMatchAndReportCompositionPreserveExistingResults(): void
    {
        $comparison = $this->fuzzyComparison('jose alvarez', 'jose alvares', 'doc-a', 'doc-a');
        $match = new DuplicateMatch(Model::class, 1, 2, $comparison);
        $service = new DuplicateMatchClassifier($this->classifier());
        $classified = $service->classify($match);
        $report = $service->classifyReport(new DuplicateReport([$match]));

        self::assertSame($comparison, $classified->comparison);
        self::assertSame('similar-candidate', $classified->classification?->label);
        self::assertEquals($classified->classification, $report->matches[0]->classification);
        self::assertNull($match->classification);
    }

    public function testClassificationContainsCodesButNoComparedValues(): void
    {
        $classification = $this->classifier()->classify(
            $this->fuzzyComparison('private-name', 'private-namf', 'secret-a', 'secret-b'),
        );
        $json = json_encode($classification, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('private-name', $json);
        self::assertStringNotContainsString('private-namf', $json);
        self::assertStringNotContainsString('secret-a', $json);
        self::assertStringNotContainsString('secret-b', $json);
    }

    #[DataProvider('invalidRules')]
    public function testInvalidRuleConfigurationIsRejected(callable $factory): void
    {
        $this->expectException(InvalidArgumentException::class);
        $factory();
    }

    /** @return iterable<string, array{callable(): object}> */
    public static function invalidRules(): iterable
    {
        yield 'empty id' => [static fn (): object => new ClassificationRule('', 'label')];
        yield 'negative evidence' => [
            static fn (): object => new ClassificationRule('rule.v1', 'label', minimumEvidence: -1),
        ];
        yield 'invalid similarity' => [
            static fn (): object => new ClassificationRule(
                'rule.v1',
                'label',
                minimumSimilarities: ['name' => 1.1],
            ),
        ];
        yield 'conflicting missing treatment' => [
            static fn (): object => new ClassificationRule(
                'rule.v1',
                'label',
                presentFields: ['name'],
                missingFields: ['name'],
            ),
        ];
        yield 'duplicate policy rules' => [static fn (): object => new ClassificationPolicy(
            'policy.v1',
            [new ClassificationRule('same.v1', 'a'), new ClassificationRule('same.v1', 'b')],
            'default',
        )];
    }

    private function fuzzyComparison(
        ?string $leftName,
        ?string $rightName,
        ?string $leftIdentity,
        ?string $rightIdentity,
    ): ComparisonResult {
        $normalizer = new TextNormalizer();
        $rule = new FuzzyRule(
            'contact.fuzzy.v1',
            [new FuzzyFieldRule(
                'name.v1',
                'name',
                $normalizer,
                new NormalizedLevenshtein(),
                0.75,
            )],
            [new ContradictionRule('identity.v1', 'identity', $normalizer)],
        );

        return (new FuzzyComparator($rule))->compare(
            ['name' => $leftName, 'identity' => $leftIdentity],
            ['name' => $rightName, 'identity' => $rightIdentity],
        );
    }
}
