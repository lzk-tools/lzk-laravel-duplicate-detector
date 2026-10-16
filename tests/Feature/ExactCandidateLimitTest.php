<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Lzk\DuplicateDetector\Configuration\DetectorConfigResolver;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use Lzk\DuplicateDetector\Exact\ExactDuplicateDetector;
use Lzk\DuplicateDetector\Exact\ExactRule;
use Lzk\DuplicateDetector\Facades\DuplicateDetector;
use Lzk\DuplicateDetector\Normalizers\EmailNormalizer;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use Lzk\DuplicateDetector\Tests\Fixtures\CodeRecord;
use Lzk\DuplicateDetector\Tests\Fixtures\ContactRecord;
use Lzk\DuplicateDetector\Tests\Fixtures\UuidRecord;
use Lzk\DuplicateDetector\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExactCandidateLimitTest extends TestCase
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
        Schema::create('detector_records', static function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id');
            $table->boolean('active')->default(true);
            $table->string('email');
            $table->string('email_key')->index();
            $table->string('identity')->nullable();
            $table->softDeletes();
        });
        $rows = [
            1 => [1, 1, 'same@example.test', 'same@example.test'],
            2 => [1, 1, ' SAME@example.test ', 'same@example.test'],
            3 => [1, 1, 'different@example.test', 'same@example.test'],
            4 => [1, 1, 'same@example.test', 'same@example.test'],
            5 => [2, 1, 'same@example.test', 'same@example.test'],
            6 => [1, 0, 'same@example.test', 'same@example.test'],
            7 => [1, 1, 'same@example.test', 'same@example.test'],
        ];
        foreach ($rows as $id => [$tenant, $active, $email, $key]) {
            DB::table('detector_records')->insert([
                'id' => $id,
                'tenant_id' => $tenant,
                'active' => $active,
                'email' => $email,
                'email_key' => $key,
                'identity' => 'id-' . $id,
                'deleted_at' => null,
            ]);
        }
    }

    private function rule(): ExactRule
    {
        return new ExactRule(
            'limit.exact.v1',
            ['email' => new EmailNormalizer(), 'identity' => new TextNormalizer()],
            ['email'],
            ['email' => 'email_key'],
            ['identity'],
        );
    }

    /** @return Builder<ContactRecord> */
    private function scope(): Builder
    {
        return ContactRecord::query()->where('tenant_id', 1);
    }

    /**
     * @param Builder<ContactRecord>|null $scope
     * @return array<int, DuplicateReport>
     */
    private function detect(int $batchSize, int $maxCandidates, ?Builder $scope = null): array
    {
        $source = ContactRecord::query()->findOrFail(1);
        DB::statement('PRAGMA query_only = ON');
        DB::flushQueryLog();
        DB::enableQueryLog();

        return iterator_to_array((new ExactDuplicateDetector($this->rule()))->detect(
            $source,
            $scope ?? $this->scope(),
            $batchSize,
            $maxCandidates,
        ));
    }

    /**
     * @param array<int, DuplicateReport> $reports
     * @return list<int|string>
     */
    private function keys(array $reports): array
    {
        $keys = [];
        foreach ($reports as $report) {
            foreach ($report->matches as $match) {
                $keys[] = $match->candidateKey;
            }
        }

        return $keys;
    }

    /** @param array<int, DuplicateReport> $reports */
    private function terminal(array $reports): DuplicateReport
    {
        $report = end($reports);
        if (!$report instanceof DuplicateReport) {
            throw new \LogicException('Expected a terminal report.');
        }

        return $report;
    }
    /** @return list<array<string, mixed>> */
    private function candidateQueries(): array
    {
        return array_values(array_filter(
            DB::getQueryLog(),
            static fn (array $entry): bool => str_contains($entry['query'], 'from "detector_records"'),
        ));
    }

    public function testFewerCandidatesThanLimitIsComplete(): void
    {
        $reports = $this->detect(1, 3, $this->scope()->whereIn('id', [2, 3]));
        $terminal = $this->terminal($reports);

        self::assertTrue($terminal->searchComplete);
        self::assertSame(2, $terminal->candidatesExamined);
        self::assertNull($terminal->incompleteReason);
        self::assertSame([2], $this->keys($reports));
    }

    public function testExactlyLimitIsConservativelyPartialWithoutProbeQuery(): void
    {
        $reports = $this->detect(10, 2, $this->scope()->whereIn('id', [2, 3]));
        $terminal = $this->terminal($reports);

        self::assertFalse($terminal->searchComplete);
        self::assertSame(2, $terminal->candidatesExamined);
        self::assertSame(DuplicateReport::MAX_CANDIDATES_REACHED, $terminal->incompleteReason);
        self::assertCount(1, $this->candidateQueries());
        self::assertStringContainsString('limit 2', $this->candidateQueries()[0]['query']);
    }

    public function testNonMatchesConsumeLimitAndPartialEmptyReportIsEmitted(): void
    {
        $reports = $this->detect(10, 1, $this->scope()->where('id', 3));

        self::assertCount(1, $reports);
        self::assertSame([], $reports[0]->matches);
        self::assertFalse($reports[0]->searchComplete);
        self::assertSame(1, $reports[0]->candidatesExamined);
    }

    #[DataProvider('batches')]
    public function testMoreCandidatesThanLimitUsesOnlyNecessaryPages(int $batch, int $queries): void
    {
        $reports = $this->detect($batch, 3);
        $terminal = $this->terminal($reports);

        self::assertFalse($terminal->searchComplete);
        self::assertSame(3, $terminal->candidatesExamined);
        self::assertSame([2, 4], $this->keys($reports));
        self::assertCount($queries, $this->candidateQueries());
        foreach (DB::getQueryLog() as $entry) {
            self::assertDoesNotMatchRegularExpression('/\b(insert|update|delete|replace|upsert)\b/i', $entry['query']);
        }
    }

    /** @return iterable<string, array{int, int}> */
    public static function batches(): iterable
    {
        yield 'smaller batch' => [2, 2];
        yield 'equal batch' => [3, 1];
        yield 'larger batch' => [10, 1];
    }

    public function testLimitPreservesTenantGlobalScopeOrBindingsAndBuilder(): void
    {
        $scope = ContactRecord::query()->where('tenant_id', 2)->orWhere(function (Builder $query): void {
            $query->where('tenant_id', 1)->whereIn('id', [2, 3, 6]);
        });
        $original = [$scope->toSql(), $scope->getBindings()];

        $reports = $this->detect(1, 2, $scope);

        self::assertSame([2], $this->keys($reports));
        self::assertSame($original, [$scope->toSql(), $scope->getBindings()]);
        foreach ($this->candidateQueries() as $query) {
            self::assertStringContainsString('"active" = ?', $query['query']);
        }
    }

    public function testLimitSupportsStringAndUuidKeys(): void
    {
        Schema::create('code_records', static function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->boolean('active');
            $table->string('email');
            $table->string('email_key')->index();
            $table->softDeletes();
        });
        foreach (['001', '002', '010'] as $code) {
            DB::table('code_records')->insert([
                'code' => $code, 'active' => 1, 'email' => 'a@x.test',
                'email_key' => 'a@x.test', 'deleted_at' => null,
            ]);
        }
        $rule = new ExactRule('code.limit.v1', ['email' => new EmailNormalizer()], ['email'], ['email' => 'email_key']);
        $codeReports = iterator_to_array((new ExactDuplicateDetector($rule))->detect(
            CodeRecord::query()->findOrFail('001'),
            CodeRecord::query(),
            10,
            1,
        ));
        self::assertSame('002', $codeReports[0]->matches[0]->candidateKey);
        self::assertFalse($codeReports[0]->searchComplete);

        Schema::create('uuid_records', static function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->boolean('active');
            $table->string('email');
            $table->string('email_key')->index();
            $table->softDeletes();
        });
        $uuids = [
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            'ffffffff-ffff-4fff-8fff-ffffffffffff',
        ];
        foreach ($uuids as $uuid) {
            DB::table('uuid_records')->insert([
                'uuid' => $uuid, 'active' => 1, 'email' => 'u@x.test',
                'email_key' => 'u@x.test', 'deleted_at' => null,
            ]);
        }
        $uuidReports = iterator_to_array((new ExactDuplicateDetector($rule))->detect(
            UuidRecord::query()->findOrFail($uuids[0]),
            UuidRecord::query(),
            10,
            1,
        ));
        self::assertSame($uuids[1], $uuidReports[0]->matches[0]->candidateKey);
        self::assertFalse($uuidReports[0]->searchComplete);
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidDirectLimitIsRejected(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactDuplicateDetector($this->rule()))->detect(
            ContactRecord::query()->findOrFail(1),
            $this->scope(),
            2,
            $limit,
        ));
    }

    /** @return iterable<string, array{int}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('invalidConfiguredLimits')]
    public function testInvalidConfiguredLimitFailsBeforeQueries(mixed $limit): void
    {
        config(['duplicate-detector.models' => [
            ContactRecord::class => ['exact' => [...$this->exactConfig(), 'max_candidates' => $limit]],
        ]]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->expectException(InvalidArgumentException::class);
        try {
            app(DetectorConfigResolver::class)->resolve(ContactRecord::class);
        } finally {
            self::assertSame([], DB::getQueryLog());
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidConfiguredLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'numeric string' => ['10'];
        yield 'float' => [10.0];
        yield 'null' => [null];
    }

    public function testPublicApiUsesConfiguredLimitAndFiniteDefault(): void
    {
        $config = $this->exactConfig();
        config(['duplicate-detector.models' => [ContactRecord::class => ['exact' => $config]]]);
        $definition = app(DetectorConfigResolver::class)->resolve(ContactRecord::class);
        self::assertSame(ExactDuplicateDetector::DEFAULT_MAX_CANDIDATES, $definition->exactMaxCandidates);

        $config['max_candidates'] = 1;
        config(['duplicate-detector.models' => [ContactRecord::class => ['exact' => $config]]]);
        $reports = iterator_to_array(DuplicateDetector::for(ContactRecord::class)
            ->scope(static fn (Builder $query): Builder => $query->where('tenant_id', 1))
            ->detect(ContactRecord::query()->findOrFail(1)));

        self::assertFalse($this->terminal($reports)->searchComplete);
        self::assertSame(1, $this->terminal($reports)->candidatesExamined);
    }

    /** @return array<string, mixed> */
    private function exactConfig(): array
    {
        return [
            'id' => 'configured.limit.v1',
            'fields' => ['email' => 'email'],
            'match_fields' => ['email'],
            'blocking' => ['email' => 'email_key'],
            'batch_size' => 2,
        ];
    }
}
