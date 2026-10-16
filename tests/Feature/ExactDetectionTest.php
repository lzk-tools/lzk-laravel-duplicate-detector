<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use Lzk\DuplicateDetector\DTO\FieldOutcome;
use Lzk\DuplicateDetector\Exact\ExactCandidateFinder;
use Lzk\DuplicateDetector\Exact\ExactDuplicateDetector;
use Lzk\DuplicateDetector\Exact\ExactRule;
use Lzk\DuplicateDetector\Normalizers\EmailNormalizer;
use Lzk\DuplicateDetector\Normalizers\PhoneNormalizer;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use Lzk\DuplicateDetector\Tests\Fixtures\CodeRecord;
use Lzk\DuplicateDetector\Tests\Fixtures\ContactRecord;
use Lzk\DuplicateDetector\Tests\Fixtures\UuidRecord;
use Lzk\DuplicateDetector\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExactDetectionTest extends TestCase
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
        Schema::create('detector_records', function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id');
            $table->boolean('active')->default(true);
            $table->string('email')->nullable();
            $table->string('email_key')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('identity')->nullable();
            $table->string('password')->nullable();
            $table->softDeletes();
        });

        $base = [
            'tenant_id' => 1,
            'active' => 1,
            'email' => 'ÉXAMPLE@EXAMPLE.COM',
            'email_key' => 'éxample@example.com',
            'name' => " ÁNA  MARÍA ",
            'phone' => '+54 (11) 1234-5678',
            'identity' => 'identity-a',
            'password' => 'never-report-this-secret',
            'deleted_at' => null,
        ];
        $overrides = [
            1 => [],
            2 => ['email' => '  éxample@example.com ', 'name' => "ána\tmaría", 'phone' => '+541112345678'],
            3 => ['tenant_id' => 2],
            4 => ['active' => 0],
            5 => ['deleted_at' => '2026-01-01 00:00:00'],
            6 => ['email' => 'other@example.com'],
            7 => ['email' => null],
            8 => ['identity' => 'identity-b'],
            9 => [],
            10 => [],
            11 => [],
            12 => ['name' => 'different'],
            13 => ['phone' => null],
            15 => ['email' => 'unrelated@example.com', 'email_key' => 'unrelated@example.com'],
        ];
        foreach ($overrides as $id => $values) {
            DB::table('detector_records')->insert(['id' => $id, ...array_replace($base, $values)]);
        }
    }

    private function rule(): ExactRule
    {
        return new ExactRule(
            'contact.exact.v1',
            [
                'email' => new EmailNormalizer(),
                'name' => new TextNormalizer(),
                'phone' => new PhoneNormalizer(),
                'identity' => new TextNormalizer(),
            ],
            ['email', 'name', 'phone'],
            ['email' => 'email_key'],
            ['identity'],
        );
    }

    /** @return Builder<ContactRecord> */
    private function scope(): Builder
    {
        return ContactRecord::query()->where('tenant_id', 1);
    }

    private function source(): ContactRecord
    {
        return ContactRecord::query()->findOrFail(1);
    }

    private function readOnly(): void
    {
        DB::statement('PRAGMA query_only = ON');
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    /** @param iterable<DuplicateReport> $reports
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

    public function testExactDetectionIsBatchedScopedDeterministicAndReadOnly(): void
    {
        $source = $this->source();
        $scope = $this->scope()->orderByDesc('name');
        $original = [$scope->toSql(), $scope->getBindings(), $scope->getQuery()->orders];
        $attributes = $source->getAttributes();
        $before = DB::table('detector_records')->orderBy('id')->get()->toJson();

        // Detection must not dispatch model hydration events.
        ContactRecord::retrieved(static function (): void {
            throw new \RuntimeException('Retrieved event must not execute during detection.');
        });
        $this->readOnly();
        $detector = new ExactDuplicateDetector($this->rule());
        $reports = iterator_to_array($detector->detect($source, $scope, 2));
        $log = DB::getQueryLog();
        self::assertSame([2, 2, 1], array_map(fn (DuplicateReport $report): int => count($report->matches), $reports));
        $matches = $this->collectMatches($reports);
        self::assertSame([2, 8, 9, 10, 11], array_column($matches, 'candidateKey'));
        self::assertSame([1, 1, 1, 1, 1], array_column($matches, 'sourceKey'));
        self::assertSame(ContactRecord::class, $matches[0]->modelClass);
        self::assertSame('contact.exact.v1', $matches[0]->comparison->ruleId);
        self::assertSame(['email', 'name', 'phone', 'identity'], $matches[0]->comparison->matchedFields);
        self::assertSame(FieldOutcome::Contradiction, $matches[1]->comparison->fieldOutcomes['identity']);
        self::assertNull($matches[1]->classification);
        self::assertNull($matches[1]->comparison->similarityScore);
        self::assertSame($original, [$scope->toSql(), $scope->getBindings(), $scope->getQuery()->orders]);
        self::assertSame($attributes, $source->getAttributes());

        $json = json_encode($reports, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('never-report', $json);
        self::assertStringNotContainsString('example.com', $json);
        self::assertStringNotContainsString('identity-b', $json);
        $again = iterator_to_array($detector->detect($source, $scope, 2));
        self::assertSame($json, json_encode($again, JSON_THROW_ON_ERROR));
        self::assertSame($before, DB::table('detector_records')->orderBy('id')->get()->toJson());

        $candidateQueries = [];
        foreach ($log as $entry) {
            self::assertMatchesRegularExpression('/^\s*(select|pragma)\b/i', $entry['query']);
            if (str_contains($entry['query'], 'from "detector_records"')) {
                $candidateQueries[] = $entry['query'];
                self::assertStringContainsString('"email_key" = ?', $entry['query']);
                self::assertStringContainsString('limit 2', $entry['query']);
                self::assertStringContainsString('order by "detector_records"."id" asc', $entry['query']);
                self::assertStringNotContainsString('password', $entry['query']);
                self::assertStringNotContainsString('select *', $entry['query']);
            }
        }
        self::assertCount(5, $candidateQueries); // Nine candidates in batches of two.
    }

    public function testTopLevelOrCannotBypassBlockingOrSourceExclusion(): void
    {
        $source = $this->source();
        $scope = ContactRecord::query()->where('id', 15)->orWhere('tenant_id', 1);
        $original = [$scope->toSql(), $scope->getBindings()];
        $this->readOnly();
        $found = iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $scope, 2));
        $keys = array_map(fn (ContactRecord $record): mixed => $record->getRawOriginal('id'), $found);

        self::assertSame([2, 6, 7, 8, 9, 10, 11, 12, 13], $keys);
        self::assertSame($original, [$scope->toSql(), $scope->getBindings()]);
    }

    public function testRestrictiveCallerFilterIsPreserved(): void
    {
        $source = $this->source();
        $this->readOnly();
        $found = $this->collectMatches((new ExactDuplicateDetector($this->rule()))->detect(
            $source,
            $this->scope()->where('id', 8),
            1,
        ));
        self::assertSame([8], array_column($found, 'candidateKey'));
    }

    public function testNoMatchesAndMissingBlockDoNotCauseGlobalScan(): void
    {
        $source = $this->source();
        $missing = ContactRecord::query()->findOrFail(7);
        $this->readOnly();
        $detector = new ExactDuplicateDetector($this->rule());

        self::assertSame([], iterator_to_array($detector->detect($source, $this->scope()->where('id', 15), 2)));
        DB::flushQueryLog();
        self::assertSame([], iterator_to_array($detector->detect($missing, $this->scope(), 2)));
        foreach (DB::getQueryLog() as $entry) {
            self::assertStringNotContainsString('from "detector_records"', $entry['query']);
        }
    }

    public function testAdditionalBlockingColumnsAreCombinedWithAnd(): void
    {
        $source = $this->source();
        // name contains noncanonical formatting; this demonstrates that every SQL block is required.
        $rule = new ExactRule(
            'two-blocks.v1',
            ['email' => new EmailNormalizer(), 'name' => new TextNormalizer()],
            ['email', 'name'],
            ['email' => 'email_key', 'name' => 'name'],
        );
        $this->readOnly();
        self::assertSame([], iterator_to_array((new ExactCandidateFinder($rule))->find($source, $this->scope(), 2)));
    }

    public function testSnapshotValuesAreUsedWithoutChangingDirtyModelValues(): void
    {
        $source = $this->source();
        $source->setAttribute('email', 'unsaved@example.com');
        $before = $source->getAttributes();
        $this->readOnly();

        $detector = new ExactDuplicateDetector($this->rule());
        $matches = $this->collectMatches($detector->detect($source, $this->scope(), 2));
        self::assertCount(5, $matches);
        self::assertSame($before, $source->getAttributes());
        self::assertTrue($source->isDirty('email'));
    }

    #[DataProvider('unsupportedQueries')]
    public function testUnsupportedQueryShapesFailClosed(string $shape): void
    {
        $source = $this->source();
        $query = $this->scope();
        match ($shape) {
            'limit' => $query->limit(1),
            'offset' => $query->offset(1),
            'select' => $query->select('id'),
            'distinct' => $query->distinct(),
            'group' => $query->groupBy('id'),
            'join' => $query->join('detector_records as other', 'other.id', '=', 'detector_records.id'),
            'lock' => $query->lockForUpdate(),
            'alias' => $query->from('detector_records as other'),
            'union' => $query->union(ContactRecord::query()),
            'scoped limit' => $query->withGlobalScope('limit', fn (Builder $builder) => $builder->limit(1)),
            default => throw new \LogicException('Unknown test query shape.'),
        };
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $query, 2));
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedQueries(): iterable
    {
        $shapes = ['limit', 'offset', 'select', 'distinct', 'group', 'join', 'lock', 'alias', 'union', 'scoped limit'];
        foreach ($shapes as $shape) {
            yield $shape => [$shape];
        }
    }

    public function testMissingDatabaseColumnIsRejectedBeforeCandidateQuery(): void
    {
        $rule = new ExactRule('missing.v1', ['email' => new EmailNormalizer()], ['email'], ['email' => 'absent_key']);
        $source = $this->source();
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactCandidateFinder($rule))->find($source, $this->scope(), 2));
    }

    public function testMissingModelAttributeIsRejected(): void
    {
        $rule = new ExactRule('missing.v1', ['absent' => new TextNormalizer()], ['absent'], ['absent' => 'email_key']);
        $source = $this->source();
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactCandidateFinder($rule))->find($source, $this->scope(), 2));
    }

    public function testUnsavedModelIsRejected(): void
    {
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactCandidateFinder($this->rule()))->find(new ContactRecord(), $this->scope(), 2));
    }

    public function testNonPositiveBatchSizeIsRejected(): void
    {
        $source = $this->source();
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $this->scope(), 0));
    }

    public function testCustomStringPrimaryKeysArePreserved(): void
    {
        Schema::create('code_records', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->boolean('active');
            $table->string('email');
            $table->string('email_key')->index();
            $table->softDeletes();
        });
        foreach (['001', '002', '010'] as $code) {
            DB::table('code_records')->insert([
                'code' => $code, 'active' => 1, 'email' => ' A@X.COM ', 'email_key' => 'a@x.com',
            ]);
        }
        $source = CodeRecord::query()->findOrFail('001');
        $rule = new ExactRule('code.v1', ['email' => new EmailNormalizer()], ['email'], ['email' => 'email_key']);
        $this->readOnly();
        $matches = $this->collectMatches((new ExactDuplicateDetector($rule))->detect($source, CodeRecord::query(), 1));
        self::assertSame(['002', '010'], array_column($matches, 'candidateKey'));
        self::assertSame(['001', '001'], array_column($matches, 'sourceKey'));
    }

    public function testExplicitlyRemovedGlobalScopeIsNotReintroduced(): void
    {
        $source = $this->source();
        $scope = $this->scope()->withoutGlobalScope('active')->where('id', 4);
        $this->readOnly();
        $matches = $this->collectMatches((new ExactDuplicateDetector($this->rule()))->detect($source, $scope, 1));
        self::assertSame([4], array_column($matches, 'candidateKey'));
        self::assertSame(['active'], $scope->removedScopes());
    }

    public function testEagerLoadsAndQueryModelAreNotMutatedOrExecuted(): void
    {
        $source = $this->source();
        $scope = $this->scope()->with('peers');
        $loads = $scope->getEagerLoads();
        $attributes = $scope->getModel()->getAttributes();
        $this->readOnly();
        $matches = $this->collectMatches((new ExactDuplicateDetector($this->rule()))->detect($source, $scope, 2));
        self::assertCount(5, $matches);
        self::assertSame($loads, $scope->getEagerLoads());
        self::assertSame($attributes, $scope->getModel()->getAttributes());
    }

    public function testDifferentModelIsRejected(): void
    {
        $source = $this->source();
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        $scope = $this->scope();
        $scope->setModel(new CodeRecord());
        iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $scope, 2));
    }

    public function testDifferentConnectionIsRejected(): void
    {
        config(['database.connections.other' => config('database.connections.testing')]);
        $source = $this->source();
        $scope = $this->scope();
        $scope->setQuery(DB::connection('other')->table('detector_records'));
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $scope, 2));
    }

    public function testPartiallyLoadedSourceIsRejected(): void
    {
        $source = ContactRecord::query()->select(['id', 'email'])->findOrFail(1);
        $this->readOnly();
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $this->scope(), 2));
    }

    public function testIntegerPrimaryKeysRemainIntegersAcrossBatches(): void
    {
        $this->readOnly();
        $matches = $this->collectMatches(
            (new ExactDuplicateDetector($this->rule()))->detect($this->source(), $this->scope(), 2),
        );

        self::assertSame([2, 8, 9, 10, 11], array_column($matches, 'candidateKey'));
        foreach ($matches as $match) {
            self::assertIsInt($match->sourceKey);
            self::assertIsInt($match->candidateKey);
        }
    }

    public function testUuidPrimaryKeysArePreservedAndOrderedLexically(): void
    {
        Schema::create('uuid_records', function (Blueprint $table): void {
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
        foreach (array_reverse($uuids) as $uuid) {
            DB::table('uuid_records')->insert([
                'uuid' => $uuid,
                'active' => 1,
                'email' => ' UUID@EXAMPLE.COM ',
                'email_key' => 'uuid@example.com',
            ]);
        }

        $source = UuidRecord::query()->findOrFail($uuids[0]);
        $rule = new ExactRule(
            'uuid.v1',
            ['email' => new EmailNormalizer()],
            ['email'],
            ['email' => 'email_key'],
        );
        $this->readOnly();
        $matches = $this->collectMatches(
            (new ExactDuplicateDetector($rule))->detect($source, UuidRecord::query(), 1),
        );

        self::assertSame([$uuids[1], $uuids[2]], array_column($matches, 'candidateKey'));
        self::assertSame([$uuids[0], $uuids[0]], array_column($matches, 'sourceKey'));
    }

    public function testOriginalBindingsPrecedeBlockingAndPaginationBindings(): void
    {
        $source = $this->source();
        $scope = ContactRecord::query()
            ->where(function (Builder $query): void {
                $query->where('tenant_id', 1)->where('name', 'like', '%MARÍA%');
            })
            ->orWhere('id', 15);
        $originalBindings = $scope->getQuery()->getBindings();
        $this->readOnly();

        iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $scope, 2));
        $candidateQueries = array_values(array_filter(
            DB::getQueryLog(),
            static fn (array $entry): bool => str_contains($entry['query'], 'from "detector_records"'),
        ));

        self::assertNotEmpty($candidateQueries);
        self::assertSame($originalBindings, array_slice($candidateQueries[0]['bindings'], 0, 3));
        self::assertSame(1, $candidateQueries[0]['bindings'][3]);
        self::assertSame('éxample@example.com', $candidateQueries[0]['bindings'][4]);
        self::assertSame(1, $candidateQueries[0]['bindings'][5]);
        self::assertStringContainsString(') or "id" = ?)', $candidateQueries[0]['query']);
    }

    public function testMissingBlockSkipsSchemaAndCandidateQueries(): void
    {
        $source = ContactRecord::query()->findOrFail(7);
        $this->readOnly();

        self::assertSame(
            [],
            iterator_to_array((new ExactCandidateFinder($this->rule()))->find($source, $this->scope(), 2)),
        );
        self::assertSame([], DB::getQueryLog());
    }

    public function testColumnValidationRunsOncePerEffectiveSearch(): void
    {
        $this->readOnly();
        iterator_to_array((new ExactCandidateFinder($this->rule()))->find($this->source(), $this->scope(), 2));

        $schemaQueries = array_filter(
            DB::getQueryLog(),
            static fn (array $entry): bool => str_contains(strtolower($entry['query']), 'pragma_table_xinfo'),
        );
        self::assertCount(1, $schemaQueries);
    }

    public function testCandidateHydrationDoesNotInvokeAccessors(): void
    {
        $this->readOnly();
        $matches = $this->collectMatches(
            (new ExactDuplicateDetector($this->rule()))->detect($this->source(), $this->scope(), 2),
        );

        self::assertCount(5, $matches);
    }

    public function testQueriesEmittedByDetectionAreReadOnly(): void
    {
        $this->readOnly();
        iterator_to_array(
            (new ExactDuplicateDetector($this->rule()))->detect($this->source(), $this->scope(), 2),
        );

        foreach (DB::getQueryLog() as $entry) {
            self::assertDoesNotMatchRegularExpression('/\b(insert|update|delete|replace|upsert)\b/i', $entry['query']);
        }
    }

    public function testRowsInsertedBetweenBatchesMayAppearInLaterPages(): void
    {
        $finder = new ExactCandidateFinder($this->rule());
        $candidates = $finder->find($this->source(), $this->scope(), 2);
        self::assertInstanceOf(\Generator::class, $candidates);
        $candidates->rewind();
        self::assertSame(2, $candidates->current()->getRawOriginal('id'));
        $candidates->next();
        self::assertSame(6, $candidates->current()->getRawOriginal('id'));

        DB::table('detector_records')->insert([
            'id' => 20,
            'tenant_id' => 1,
            'active' => 1,
            'email' => ' éxample@example.com ',
            'email_key' => 'éxample@example.com',
            'name' => 'ána maría',
            'phone' => '+541112345678',
            'identity' => 'identity-a',
            'password' => 'external-writer',
            'deleted_at' => null,
        ]);

        $ids = [2, 6];
        $candidates->next();
        while ($candidates->valid()) {
            $ids[] = $candidates->current()->getRawOriginal('id');
            $candidates->next();
        }

        self::assertSame([2, 6, 7, 8, 9, 10, 11, 12, 13, 20], $ids);
    }
}
