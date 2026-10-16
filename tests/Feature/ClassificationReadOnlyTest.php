<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lzk\DuplicateDetector\Classification\ClassificationPolicy;
use Lzk\DuplicateDetector\Classification\ClassificationRule;
use Lzk\DuplicateDetector\Classification\DeterministicRiskClassifier;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\Tests\TestCase;

final class ClassificationReadOnlyTest extends TestCase
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

    public function testClassificationExecutesNoDatabaseStatements(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $classifier = new DeterministicRiskClassifier(new ClassificationPolicy(
            'read-only.v1',
            [new ClassificationRule('review.v1', 'review', minimumEvidence: 1)],
            'none',
        ));

        $classification = $classifier->classify(new ComparisonResult(evidence: ['safe.code']));

        self::assertSame('review', $classification->label);
        self::assertSame([], DB::getQueryLog());
    }
}
