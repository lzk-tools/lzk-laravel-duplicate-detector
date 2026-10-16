<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Lzk\DuplicateDetector\Configuration\DetectorConfigResolver;

final class DuplicateDetectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/duplicate-detector.php', 'duplicate-detector');

        $this->app->singleton(
            DetectorConfigResolver::class,
            static fn (Application $app): DetectorConfigResolver => new DetectorConfigResolver(
                $app->make(Repository::class),
            ),
        );
        $this->app->singleton(
            DuplicateDetectorManager::class,
            static fn (Application $app): DuplicateDetectorManager => new DuplicateDetectorManager(
                $app->make(DetectorConfigResolver::class),
            ),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__) . '/config/duplicate-detector.php' => config_path('duplicate-detector.php'),
            ], 'duplicate-detector-config');
        }
    }
}
