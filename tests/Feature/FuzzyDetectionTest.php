<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lzk\DuplicateDetector\Classification\ClassificationPolicy;
use Lzk\DuplicateDetector\Classification\ClassificationRule;
use Lzk\DuplicateDetector\Classification\ContradictionRequirement;
use Lzk\DuplicateDetector\Classification\DeterministicRiskClassifier;
use Lzk\DuplicateDetector\Classification\DuplicateMatchClassifier;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use Lzk\DuplicateDetector\DTO\FieldOutcome;
use Lzk\DuplicateDetector\Fuzzy\ContradictionRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyCandidateFinder;
use Lzk\DuplicateDetector\Fuzzy\FuzzyDuplicateDetector;
use Lzk\DuplicateDetector\Fuzzy\FuzzyFieldRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyLimits;
use Lzk\DuplicateDetector\Fuzzy\FuzzyRule;
use Lzk\DuplicateDetector\Fuzzy\SqlBlock;
use Lzk\DuplicateDetector\Fuzzy\SqlBlockingStrategy;
use Lzk\DuplicateDetector\Metrics\NormalizedLevenshtein;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use Lzk\DuplicateDetector\Tests\Fixtures\FuzzyRecord;
use Lzk\DuplicateDetector\Tests\TestCase;

final class FuzzyDetectionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('fuzzy_records', function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id');
            $table->boolean('active')->default(true);
            $table->string('surname_bucket')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('alias')->nullable();
            $table->string('identity')->nullable();
            $table->string('secret')->nullable();
            $table->softDeletes();
        });

        $base = [
            'tenant_id' => 1,
            'active' => 1,
            'surname_bucket' => 'alvarez',
            'name' => 'José Álvarez',
            'alias' => 'Pepe',
            'identity' => 'DOC-A',
            'secret' => 'never-report-fuzzy-secret',
            'deleted_at' => null,
        ];
        $rows = [
            1 => [],
            2 => ['name' => " JOSÉ\t ÁLVAREZ "],
            3 => ['name' => 'José Alvares', 'identity' => 'DOC-B'],
            4 => ['name' => 'Completely Other'],
            5 => ['tenant_id' => 2],
            6 => ['active' => 0],
            7 => ['deleted_at' => '2026-01-01 00:00:00'],
            8 => ['name' => null],
            9 => ['name' => 'José Álvares', 'identity' => null],
            10 => ['surname_bucket' => 'other'],
            11 => ['name' => 'Jose Álvarez', 'alias' => null],
            12 => ['name' => 'José Álvarez', 'alias' => 'Pep'],
            13 => ['surname_bucket' => null],
        ];
        foreach ($rows as $id => $overrides) {
            DB::table('fuzzy_records')->insert(['id' => $id, ...array_replace($base, $overrides)]);
        }
    }

    private function rule(): FuzzyRule
    {
        return new FuzzyRule(
            'person.fuzzy.v1',
            [
                new FuzzyFieldRule(
                    'person.name.similarity.v1',
                    'name',
                    new TextNormalizer(),
                    new NormalizedLevenshtein(128),
                    0.75,
                    true,
                ),
                new FuzzyFieldRule(
                    'person.alias.similarity.v1',
                    'alias',
                    new TextNormalizer(),
                    new NormalizedLevenshtein(128),
                    0.60,
                    false,
                ),
            ],
            [new ContradictionRule('person.identity.different.v1', 'identity', new TextNormalizer())],
        );
    }

    private function blocking(): SqlBlockingStrategy
    {
        return new SqlBlockingStrategy('person.surname.block.v1', [
            new SqlBlock('surname_bucket', 'surname_bucket', new TextNormalizer()),
        ]);
    }

    private function limits(
        int $candidates = 100,
        int $comparisons = 100,
        int $fetch = 2,
        int $report = 2,
    ): FuzzyLimits {
        return new FuzzyLimits($candidates, $comparisons, $fetch, $report);
    }

    /** @return Builder<FuzzyRecord> */
    private function scope(): Builder
    {
        return FuzzyRecord::query()->where('tenant_id', 1);
    }

    private function source(int $id = 1): FuzzyRecord
    {
        return FuzzyRecord::query()->findOrFail($id);
    }

    private function readOnly(): void
    {
        DB::statement('PRAGMA query_only = ON');
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    /**
     * @param iterable<DuplicateReport> $reports
     * @return list<DuplicateMatch>
     */
    private function collectMatches(iterable $reports): array
    {
        $matches = [];
        foreach ($reports as $report) {
            array_push($matches, ...$report->matches);
        }

        return $matches;
    }

    public function testFuzzyDetectionIsScopedBatchedExplainableAndReadOnly(): void
    {
        $source = $this->source();
        $scope = $this->scope()->orderByDesc('name');
        $builderState = [$scope->toSql(), $scope->getBindings(), $scope->getQuery()->orders];
        $sourceState = $source->getAttributes();
        FuzzyRecord::retrieved(static function (): void {
            throw new \RuntimeException('Fuzzy candidate hydration must not dispatch retrieved.');
        });
        $this->readOnly();

        $detector = new FuzzyDuplicateDetector($this->rule(), $this->blocking());
        $reports = iterator_to_array($detector->detect($source, $scope, $this->limits()));
        self::assertSame([2, 2, 1], array_map(
            static fn (DuplicateReport $report): int => count($report->matches),
            $reports,
        ));
        $matches = $this->collectMatches($reports);
        self::assertSame([2, 3, 9, 11, 12], array_column($matches, 'candidateKey'));
        self::assertSame($builderState, [$scope->toSql(), $scope->getBindings(), $scope->getQuery()->orders]);
        self::assertSame($sourceState, $source->getAttributes());
        self::assertSame('person.fuzzy.v1', $matches[0]->comparison->ruleId);
        self::assertSame(1.0, $matches[0]->comparison->fieldComparisons['name']->similarity);
        self::assertSame(
            FieldOutcome::Contradiction,
            $matches[1]->comparison->fieldOutcomes['identity'],
        );
        self::assertSame(
            ['contradiction.person.identity.different.v1'],
            $matches[1]->comparison->contradictions,
        );
        self::assertNull($matches[1]->classification);
        self::assertNull($matches[1]->comparison->similarityScore);

        $json = json_encode($reports, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('José', $json);
        self::assertStringNotContainsString('DOC-', $json);
        self::assertStringNotContainsString('never-report', $json);
        self::assertSame(
            $json,
            json_encode(iterator_to_array($detector->detect($source, $scope, $this->limits())), JSON_THROW_ON_ERROR),
        );

        foreach (DB::getQueryLog() as $entry) {
            self::assertDoesNotMatchRegularExpression('/\b(insert|update|delete|replace|upsert)\b/i', $entry['query']);
            if (str_contains($entry['query'], 'from "fuzzy_records"')) {
                self::assertStringContainsString('"surname_bucket" = ?', $entry['query']);
                self::assertStringContainsString('limit 2', $entry['query']);
                self::assertStringNotContainsString('secret', $entry['query']);
                self::assertStringNotContainsString('select *', $entry['query']);
            }
        }
    }

    public function testTenantGlobalScopeOrGroupingAndBlockingArePreserved(): void
    {
        $scope = FuzzyRecord::query()->where('id', 10)->orWhere('tenant_id', 1);
        $original = [$scope->toSql(), $scope->getBindings()];
        $this->readOnly();
        $candidates = iterator_to_array((new FuzzyCandidateFinder(
            $this->rule(),
            $this->blocking(),
        ))->find($this->source(), $scope, $this->limits()));
        $ids = array_map(static fn (FuzzyRecord $row): mixed => $row->getRawOriginal('id'), $candidates);

        self::assertSame([2, 3, 4, 8, 9, 11, 12], $ids);
        self::assertSame($original, [$scope->toSql(), $scope->getBindings()]);
    }

    public function testCandidateAndComparisonLimitsAreIndependentHardCaps(): void
    {
        $detector = new FuzzyDuplicateDetector($this->rule(), $this->blocking());
        $this->readOnly();
        $candidateLimited = $this->collectMatches($detector->detect(
            $this->source(),
            $this->scope(),
            $this->limits(candidates: 1, comparisons: 10, fetch: 5, report: 10),
        ));
        self::assertSame([2], array_column($candidateLimited, 'candidateKey'));
        foreach (DB::getQueryLog() as $entry) {
            if (str_contains($entry['query'], 'from "fuzzy_records"')) {
                self::assertStringContainsString('limit 1', $entry['query']);
            }
        }

        DB::flushQueryLog();
        $comparisonLimited = $this->collectMatches($detector->detect(
            $this->source(),
            $this->scope(),
            $this->limits(candidates: 10, comparisons: 2, fetch: 5, report: 10),
        ));
        self::assertSame([2, 3], array_column($comparisonLimited, 'candidateKey'));
    }

    public function testZeroCandidatesAndMissingBlockReturnNoReports(): void
    {
        $detector = new FuzzyDuplicateDetector($this->rule(), $this->blocking());
        $source = $this->source();
        $missingBlock = $this->source(13);
        $this->readOnly();

        self::assertSame([], iterator_to_array($detector->detect(
            $source,
            $this->scope()->where('id', 10),
            $this->limits(),
        )));
        DB::flushQueryLog();
        self::assertSame([], iterator_to_array($detector->detect(
            $missingBlock,
            $this->scope(),
            $this->limits(),
        )));
        self::assertSame([], DB::getQueryLog());
    }

    public function testBlockingCanProduceDocumentedFalseNegative(): void
    {
        $this->readOnly();
        $matches = $this->collectMatches((new FuzzyDuplicateDetector(
            $this->rule(),
            $this->blocking(),
        ))->detect($this->source(), $this->scope(), $this->limits()));

        self::assertNotContains(10, array_column($matches, 'candidateKey'));
        self::assertSame('José Álvarez', DB::table('fuzzy_records')->where('id', 10)->value('name'));
    }

    public function testDetectedMatchesCanBeClassifiedWithoutWrites(): void
    {
        $this->readOnly();
        $reports = iterator_to_array((new FuzzyDuplicateDetector(
            $this->rule(),
            $this->blocking(),
        ))->detect($this->source(), $this->scope(), $this->limits()));
        $service = new DuplicateMatchClassifier(new DeterministicRiskClassifier(new ClassificationPolicy(
            'fuzzy-review.v1',
            [
                new ClassificationRule(
                    'identity-review.v1',
                    'manual-review',
                    contradictions: ContradictionRequirement::Present,
                ),
                new ClassificationRule(
                    'name-similar.v1',
                    'similar-candidate',
                    comparisonRuleIds: ['person.fuzzy.v1'],
                    minimumSimilarities: ['name' => 0.75],
                ),
            ],
            'unclassified',
        )));
        $classified = array_map($service->classifyReport(...), $reports);
        $matches = $this->collectMatches($classified);
        $labelsByCandidate = [];
        foreach ($matches as $match) {
            $labelsByCandidate[$match->candidateKey] = $match->classification?->label;
        }

        self::assertSame('similar-candidate', $labelsByCandidate[2]);
        self::assertSame('manual-review', $labelsByCandidate[3]);
        self::assertSame(
            ['contradiction.person.identity.different.v1'],
            $matches[1]->comparison->contradictions,
        );
        foreach (DB::getQueryLog() as $entry) {
            self::assertDoesNotMatchRegularExpression('/\b(insert|update|delete|replace|upsert)\b/i', $entry['query']);
        }
    }
}
