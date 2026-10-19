<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\PendingCommand;
use Lzk\DuplicateDetector\DuplicateDetectorServiceProvider;
use Lzk\DuplicateDetector\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function testProviderBootsWithoutADatabase(): void
    {
       $app = $this->app;

        if ($app === null) {
            self::fail('Laravel application was not initialized.');
        }

        self::assertTrue($app->isBooted());
        self::assertInstanceOf(
            DuplicateDetectorServiceProvider::class,
            $app->getProvider(DuplicateDetectorServiceProvider::class),
        );
        self::assertSame([], config('database.connections'));
        self::assertSame([], config('duplicate-detector.models'));
        self::assertSame(500, config('duplicate-detector.batch_size'));
    }

    public function testConfigurationCanBePublished(): void
    {
        $target = config_path('duplicate-detector.php');
        $source = dirname(__DIR__, 2) . '/config/duplicate-detector.php';
        $previous = is_file($target) ? file_get_contents($target) : null;

        try {
            $paths = ServiceProvider::pathsToPublish(
                DuplicateDetectorServiceProvider::class,
                'duplicate-detector-config',
            );
            self::assertSame($target, $paths[$source]);
            $command = $this->artisan('vendor:publish', [
                '--provider' => DuplicateDetectorServiceProvider::class,
                '--tag' => 'duplicate-detector-config',
                '--force' => true,
            ]);
            self::assertInstanceOf(PendingCommand::class, $command);
            self::assertSame(0, $command->run());
            self::assertFileEquals($source, $target);
        } finally {
            if (is_string($previous)) {
                file_put_contents($target, $previous);
            } elseif (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testConsumerConfigurationTakesPrecedence(): void
    {
        self::assertNotNull($this->app);
        config(['duplicate-detector.batch_size' => 25]);
        (new DuplicateDetectorServiceProvider($this->app))->register();

        self::assertSame(25, config('duplicate-detector.batch_size'));
        self::assertSame([], config('duplicate-detector.models'));
    }
}
