<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use Lzk\DuplicateDetector\Contracts\CandidateFinderInterface;
use Lzk\DuplicateDetector\Contracts\ComparatorInterface;
use Lzk\DuplicateDetector\Contracts\NormalizerInterface;
use Lzk\DuplicateDetector\Contracts\RiskClassifierInterface;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use Lzk\DuplicateDetector\DTO\RiskClassification;
use Lzk\DuplicateDetector\DuplicateDetectorServiceProvider;
use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase
{
    public function testPublicTypesAutoloadThroughComposer(): void
    {
        foreach (
            [
            CandidateFinderInterface::class,
            ComparatorInterface::class,
            NormalizerInterface::class,
            RiskClassifierInterface::class,
            ] as $interface
        ) {
            self::assertTrue(interface_exists($interface));
        }

        foreach (
            [
            ComparisonResult::class,
            DuplicateMatch::class,
            DuplicateReport::class,
            RiskClassification::class,
            DuplicateDetectorServiceProvider::class,
            ] as $class
        ) {
            self::assertTrue(class_exists($class));
        }
    }

    public function testComposerDeclaresPackageDiscovery(): void
    {
        $json = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        self::assertIsString($json);
        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            [DuplicateDetectorServiceProvider::class],
            $manifest['extra']['laravel']['providers'],
        );
    }
}
