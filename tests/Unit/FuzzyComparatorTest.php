<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use Lzk\DuplicateDetector\DTO\FieldOutcome;
use Lzk\DuplicateDetector\Fuzzy\ContradictionRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyComparator;
use Lzk\DuplicateDetector\Fuzzy\FuzzyFieldRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyRule;
use Lzk\DuplicateDetector\Metrics\NormalizedLevenshtein;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class FuzzyComparatorTest extends TestCase
{
    private function comparator(): FuzzyComparator
    {
        return new FuzzyComparator(new FuzzyRule(
            'person.fuzzy.v1',
            [
                new FuzzyFieldRule(
                    'person.name.v1',
                    'name',
                    new TextNormalizer(),
                    new NormalizedLevenshtein(),
                    0.75,
                    true,
                ),
                new FuzzyFieldRule(
                    'person.alias.v1',
                    'alias',
                    new TextNormalizer(),
                    new NormalizedLevenshtein(),
                    0.5,
                    false,
                ),
            ],
            [new ContradictionRule('person.identity.v1', 'identity', new TextNormalizer())],
        ));
    }

    public function testHighSimilarityCanCoexistWithExplicitContradiction(): void
    {
        $result = $this->comparator()->compare(
            ['name' => 'josé álvarez', 'alias' => 'pepe', 'identity' => 'doc-a'],
            ['name' => 'josé álvares', 'alias' => 'pepe', 'identity' => 'doc-b'],
        );

        self::assertTrue($this->comparator()->qualifies($result));
        self::assertSame(FieldOutcome::Match, $result->fieldOutcomes['name']);
        self::assertSame(FieldOutcome::Contradiction, $result->fieldOutcomes['identity']);
        self::assertSame(['contradiction.person.identity.v1'], $result->contradictions);
        self::assertGreaterThanOrEqual(0.75, $result->fieldComparisons['name']->similarity);
        self::assertNull($result->similarityScore);
        self::assertNull($result->metric);
        self::assertStringNotContainsString('doc-a', json_encode($result, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('doc-b', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testLowSimilarityWithoutContradictionDoesNotQualify(): void
    {
        $result = $this->comparator()->compare(
            ['name' => 'josé álvarez', 'alias' => 'pepe', 'identity' => 'doc-a'],
            ['name' => 'different', 'alias' => 'other', 'identity' => 'doc-a'],
        );

        self::assertFalse($this->comparator()->qualifies($result));
        self::assertSame(FieldOutcome::Difference, $result->fieldOutcomes['name']);
        self::assertSame([], $result->contradictions);
    }

    public function testMissingValuesDoNotRunMetricOrBecomeEvidenceOfDuplication(): void
    {
        $result = $this->comparator()->compare(
            ['name' => null, 'alias' => '', 'identity' => null],
            ['name' => null, 'alias' => '', 'identity' => 'doc-a'],
        );

        self::assertFalse($this->comparator()->qualifies($result));
        self::assertNull($result->fieldComparisons['name']->similarity);
        self::assertSame(FieldOutcome::Missing, $result->fieldOutcomes['name']);
        self::assertSame(FieldOutcome::Missing, $result->fieldOutcomes['alias']);
        self::assertSame(FieldOutcome::Missing, $result->fieldOutcomes['identity']);
        self::assertSame([], $result->contradictions);
    }

    public function testExactTextProducesPerFieldSimilarityOne(): void
    {
        $result = $this->comparator()->compare(
            ['name' => 'same', 'alias' => 'same', 'identity' => 'same'],
            ['name' => 'same', 'alias' => 'same', 'identity' => 'same'],
        );

        self::assertTrue($this->comparator()->qualifies($result));
        self::assertSame(1.0, $result->fieldComparisons['name']->similarity);
        self::assertSame('levenshtein.normalized.codepoints.v1', $result->fieldComparisons['name']->metric);
        self::assertSame('person.name.v1', $result->fieldComparisons['name']->ruleId);
    }
}
