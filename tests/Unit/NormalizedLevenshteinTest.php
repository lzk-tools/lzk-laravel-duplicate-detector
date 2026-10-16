<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Metrics\NormalizedLevenshtein;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NormalizedLevenshteinTest extends TestCase
{
    #[DataProvider('similarities')]
    public function testNormalizedSimilarity(string $left, string $right, float $expected): void
    {
        $metric = new NormalizedLevenshtein();
        self::assertEqualsWithDelta($expected, $metric->similarity($left, $right), 0.000000000001);
        self::assertEqualsWithDelta($expected, $metric->similarity($right, $left), 0.000000000001);
    }

    /** @return iterable<string, array{string, string, float}> */
    public static function similarities(): iterable
    {
        yield 'exact equality' => ['same', 'same', 1.0];
        yield 'both empty convention' => ['', '', 1.0];
        yield 'one empty' => ['', 'abc', 0.0];
        yield 'canonical example' => ['kitten', 'sitting', 1.0 - (3.0 / 7.0)];
        yield 'single code-point accent' => ['jose', 'josé', 0.75];
        yield 'unicode emoji code points' => ['🙂a', '🙂b', 0.5];
        yield 'completely different' => ['abc', 'xyz', 0.0];
    }

    public function testTextNormalizationPrecedesMetricWithoutRemovingAccents(): void
    {
        $normalizer = new TextNormalizer();
        $metric = new NormalizedLevenshtein();
        $left = $normalizer->normalize("  JOSÉ\tÁLVAREZ ");
        $right = $normalizer->normalize('josé álvarez');
        self::assertIsString($left);
        self::assertIsString($right);
        self::assertSame(1.0, $metric->similarity($left, $right));
        self::assertLessThan(1.0, $metric->similarity($left, 'jose alvarez'));
    }

    public function testLongInputsAreDeterministicWithinConfiguredLimit(): void
    {
        $metric = new NormalizedLevenshtein(256);
        $left = str_repeat('á', 255) . 'a';
        $right = str_repeat('á', 255) . 'b';
        $expected = 1.0 - (1.0 / 256.0);
        self::assertSame($expected, $metric->similarity($left, $right));
    }

    public function testConfiguredLengthLimitFailsBeforeQuadraticWork(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NormalizedLevenshtein(8))->similarity(str_repeat('a', 9), 'a');
    }

    public function testInvalidUtf8IsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NormalizedLevenshtein())->similarity("\xFF", 'a');
    }

    public function testInvalidMaximumLengthIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NormalizedLevenshtein(0);
    }
}
