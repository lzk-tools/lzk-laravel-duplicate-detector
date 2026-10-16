<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests;

use Illuminate\Foundation\Application;
use Lzk\DuplicateDetector\DuplicateDetectorServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [DuplicateDetectorServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        // Any accidental database access fails: no database service or schema is needed.
        $app['config']->set('database.default', 'unconfigured');
        $app['config']->set('database.connections', []);
    }
}
