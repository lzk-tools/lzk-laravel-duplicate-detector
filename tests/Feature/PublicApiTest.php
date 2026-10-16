<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Lzk\DuplicateDetector\Configuration\DetectorConfigResolver;
use Lzk\DuplicateDetector\DuplicateDetectorManager;
use Lzk\DuplicateDetector\Facades\DuplicateDetector;
use Lzk\DuplicateDetector\Tests\Fixtures\ConfiguredRecord;
use Lzk\DuplicateDetector\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('configured_records', static function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id');
            $table->boolean('active');
            $table->string('email');
            $table->string('name_bucket');
            $table->string('display_name')->nullable();
            $table->string('identity')->nullable();
            $table->string('secret')->nullable();
        });

        $base = [
            'tenant_id' => 1,
            'active' => 1,
            'email' => 'person@example.test',
            'name_bucket' => 'alvarez',
            'display_name' => 'José Álvarez',
            'identity' => 'doc-a',
            'secret' => 'never-report-configured-secret',
        ];
        $rows = [
            1 => [],
            2 => ['display_name' => 'Jose Alvarez'],
            3 => ['display_name' => 'José Alvares', 'identity' => 'doc-b'],
            4 => ['display_name' => 'Unrelated', 'email' => 'other@example.test'],
            5 => ['tenant_id' => 2],
            6 => ['active' => 0],
            7 => ['name_bucket' => 'other'],
        ];
        foreach ($rows as $id => $overrides) {
            DB::table('configured_records')->insert(['id' => $id, ...array_replace($base, $overrides)]);
        }

        config(['duplicate-detector.models' => $this->validConfiguration()]);
    }

    /** @return array<class-string<ConfiguredRecord>, array<string, mixed>> */
    private function validConfiguration(): array
    {
        return [
            ConfiguredRecord::class => [
                'exact' => [
                    'id' => 'contact.email.exact.v1',
                    'fields' => ['email' => 'email', 'identity' => 'text'],
                    'match_fields' => ['email'],
                    'blocking' => ['email' => 'email'],
                    'contradiction_fields' => ['identity'],
                    'batch_size' => 2,
                ],
                'fuzzy' => [
                    'id' => 'contact.name.fuzzy.v1',
                    'fields' => [
                        'display_name' => [
                            'id' => 'contact.name.similarity.v1',
                            'normalizer' => 'text',
                            'metric' => 'normalized_levenshtein',
                            'max_code_points' => 128,
                            'threshold' => 0.75,
                            'required' => true,
                        ],
                    ],
                    'contradictions' => [
                        'identity' => [
                            'id' => 'contact.identity.different.v1',
                            'normalizer' => 'text',
                        ],
                    ],
                    'blocking' => [
                        'id' => 'contact.name-bucket.v1',
                        'blocks' => [[
                            'source_field' => 'name_bucket',
                            'column' => 'name_bucket',
                            'normalizer' => 'text',
                        ]],
                    ],
                    'limits' => [
                        'max_candidates' => 10,
                        'max_comparisons' => 10,
                        'fetch_batch_size' => 2,
                        'report_batch_size' => 2,
                    ],
                ],
                'classification' => [
                    'id' => 'contact.review.v1',
                    'default_label' => 'not-classified',
                    'rules' => [
                        [
                            'id' => 'contradiction-review.v1',
                            'label' => 'manual-review',
                            'contradictions' => 'present',
                        ],
                        [
                            'id' => 'exact-email.v1',
                            'label' => 'exact-candidate',
                            'comparison_rule_ids' => ['contact.email.exact.v1'],
                            'matched_fields' => ['email'],
                            'contradictions' => 'none',
                            'minimum_evidence' => 1,
                        ],
                        [
                            'id' => 'similar-name.v1',
                            'label' => 'similar-candidate',
                            'comparison_rule_ids' => ['contact.name.fuzzy.v1'],
                            'minimum_similarities' => ['display_name' => 0.75],
                            'present_fields' => ['display_name'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testManagerAndResolverAreContainerSingletons(): void
    {
        $app = $this->app;
        self::assertNotNull($app);
        $manager = $app->make(DuplicateDetectorManager::class);
        self::assertSame($manager, $app->make(DuplicateDetectorManager::class));
        self::assertSame(
            $app->make(DetectorConfigResolver::class),
            $app->make(DetectorConfigResolver::class),
        );
    }

    public function testFacadeRunsConfiguredExactFuzzyAndClassificationPipelines(): void
    {
        $source = ConfiguredRecord::query()->findOrFail(1);
        $capturedScope = null;
        $scopeState = null;
        DB::statement('PRAGMA query_only = ON');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $detector = DuplicateDetector::for(ConfiguredRecord::class)->scope(
            static function (Builder $query) use (&$capturedScope, &$scopeState): Builder {
                $capturedScope = $query->where('tenant_id', 1);
                $scopeState = [$capturedScope->toSql(), $capturedScope->getBindings()];

                return $capturedScope;
            },
        );
        $reports = iterator_to_array($detector->detect($source));
        $matches = array_merge(...array_map(static fn ($report): array => $report->matches, $reports));

        self::assertNotSame([], $matches);
        self::assertSame('contact.email.exact.v1', $matches[0]->comparison->ruleId);
        self::assertSame('exact-candidate', $matches[0]->classification?->label);
        self::assertContains('contact.name.fuzzy.v1', array_map(
            static fn ($match): ?string => $match->comparison->ruleId,
            $matches,
        ));
        $manualReview = array_values(array_filter(
            $matches,
            static fn ($match): bool => $match->classification?->label === 'manual-review',
        ));
        self::assertNotSame([], $manualReview);
        self::assertNotSame([], $manualReview[0]->comparison->contradictions);
        self::assertInstanceOf(Builder::class, $capturedScope);
        self::assertSame($scopeState, [$capturedScope->toSql(), $capturedScope->getBindings()]);

        $json = json_encode($reports, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('José', $json);
        self::assertStringNotContainsString('doc-a', $json);
        self::assertStringNotContainsString('never-report', $json);
        foreach (DB::getQueryLog() as $entry) {
            self::assertDoesNotMatchRegularExpression('/\b(insert|update|delete|replace|upsert)\b/i', $entry['query']);
            if (str_contains($entry['query'], 'from "configured_records"')) {
                self::assertStringContainsString('"tenant_id" = ?', $entry['query']);
            }
        }
    }

    public function testExplicitScopeIsMandatoryAndNoQueryRunsWithoutIt(): void
    {
        $app = $this->app;
        self::assertNotNull($app);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $detector = $app->make(DuplicateDetectorManager::class)->for(ConfiguredRecord::class);

        $this->expectException(LogicException::class);
        try {
            $detector->detect(new ConfiguredRecord());
        } finally {
            self::assertSame([], DB::getQueryLog());
        }
    }

    public function testScopeTenantGlobalScopeAndConfiguredLimitsArePreserved(): void
    {
        $configuration = $this->validConfiguration();
        unset($configuration[ConfiguredRecord::class]['exact']);
        $configuration[ConfiguredRecord::class]['fuzzy']['limits']['max_candidates'] = 1;
        $configuration[ConfiguredRecord::class]['fuzzy']['limits']['max_comparisons'] = 1;
        config(['duplicate-detector.models' => $configuration]);

        $source = ConfiguredRecord::query()->findOrFail(1);
        $reports = iterator_to_array(DuplicateDetector::for(ConfiguredRecord::class)
            ->scope(static fn (Builder $query): Builder => $query->where('tenant_id', 1))
            ->detect($source));
        $matches = array_merge(...array_map(static fn ($report): array => $report->matches, $reports));

        self::assertCount(1, $matches);
        self::assertSame(2, $matches[0]->candidateKey);
    }

    public function testExactOnlyConfigurationUsesTheExistingExactEngine(): void
    {
        $configuration = $this->validConfiguration();
        unset($configuration[ConfiguredRecord::class]['fuzzy']);
        config(['duplicate-detector.models' => $configuration]);
        $source = ConfiguredRecord::query()->findOrFail(1);

        $reports = iterator_to_array(DuplicateDetector::for(ConfiguredRecord::class)
            ->scope(static fn (Builder $query): Builder => $query->where('tenant_id', 1))
            ->detect($source));
        $matches = array_merge(...array_map(static fn ($report): array => $report->matches, $reports));

        self::assertNotSame([], $matches);
        foreach ($matches as $match) {
            self::assertSame('contact.email.exact.v1', $match->comparison->ruleId);
        }
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationFailsBeforeQueries(callable $mutate): void
    {
        $app = $this->app;
        self::assertNotNull($app);
        $configuration = $this->validConfiguration();
        $mutate($configuration);
        config(['duplicate-detector.models' => $configuration]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->expectException(InvalidArgumentException::class);
        try {
            $app->make(DuplicateDetectorManager::class)->for(ConfiguredRecord::class);
        } finally {
            self::assertSame([], DB::getQueryLog());
        }
    }

    /** @return iterable<string, array{callable(array<class-string<ConfiguredRecord>, array<string, mixed>>&): void}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'unknown normalizer' => [static function (array &$config): void {
            $config[ConfiguredRecord::class]['exact']['fields']['email'] = 'unknown';
        }];
        yield 'missing fuzzy blocking' => [static function (array &$config): void {
            unset($config[ConfiguredRecord::class]['fuzzy']['blocking']);
        }];
        yield 'invalid threshold' => [static function (array &$config): void {
            $config[ConfiguredRecord::class]['fuzzy']['fields']['display_name']['threshold'] = 1.1;
        }];
        yield 'unknown contradiction mode' => [static function (array &$config): void {
            $config[ConfiguredRecord::class]['classification']['rules'][0]['contradictions'] = 'always';
        }];
        yield 'no engine' => [static function (array &$config): void {
            unset($config[ConfiguredRecord::class]['exact'], $config[ConfiguredRecord::class]['fuzzy']);
        }];
    }

    public function testUnconfiguredModelIsRejectedWithoutQueries(): void
    {
        config(['duplicate-detector.models' => []]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->expectException(InvalidArgumentException::class);
        try {
            DuplicateDetector::for(ConfiguredRecord::class);
        } finally {
            self::assertSame([], DB::getQueryLog());
        }
    }
}
