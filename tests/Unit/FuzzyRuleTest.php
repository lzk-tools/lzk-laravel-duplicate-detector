<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Fuzzy\ContradictionRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyFieldRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyLimits;
use Lzk\DuplicateDetector\Fuzzy\FuzzyRule;
use Lzk\DuplicateDetector\Fuzzy\SqlBlock;
use Lzk\DuplicateDetector\Fuzzy\SqlBlockingStrategy;
use Lzk\DuplicateDetector\Metrics\NormalizedLevenshtein;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FuzzyRuleTest extends TestCase
{
    private static function field(string $id = 'name.v1', bool $required = true): FuzzyFieldRule
    {
        return new FuzzyFieldRule(
            $id,
            'name',
            new TextNormalizer(),
            new NormalizedLevenshtein(),
            0.8,
            $required,
        );
    }

    public function testRuleKeepsFuzzyFieldsAndContradictionsExplicit(): void
    {
        $rule = new FuzzyRule(
            'person.v1',
            [self::field()],
            [new ContradictionRule('identity.v1', 'identity', new TextNormalizer())],
        );
        self::assertSame(['name', 'identity'], $rule->selectedFields());
    }

    #[DataProvider('invalidThresholds')]
    public function testInvalidThresholdsAreRejected(float $threshold): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FuzzyFieldRule(
            'name.v1',
            'name',
            new TextNormalizer(),
            new NormalizedLevenshtein(),
            $threshold,
        );
    }

    /** @return iterable<string, array{float}> */
    public static function invalidThresholds(): iterable
    {
        yield 'negative' => [-0.01];
        yield 'over one' => [1.01];
        yield 'infinite' => [INF];
        yield 'not a number' => [NAN];
    }

    public function testAtLeastOneRequiredFieldIsNecessary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FuzzyRule('person.v1', [self::field(required: false)]);
    }

    public function testDuplicateRuleIdentifiersAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FuzzyRule(
            'person.v1',
            [self::field('shared.v1')],
            [new ContradictionRule('shared.v1', 'identity', new TextNormalizer())],
        );
    }

    public function testContradictionFieldsCannotOverwriteFuzzyOutcomes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FuzzyRule(
            'person.v1',
            [self::field()],
            [new ContradictionRule('name.contradiction.v1', 'name', new TextNormalizer())],
        );
    }

    public function testBlockingRequiresAtLeastOneUniqueSqlBlock(): void
    {
        $block = new SqlBlock('bucket', 'bucket_key', new TextNormalizer());
        $this->expectException(InvalidArgumentException::class);
        new SqlBlockingStrategy('block.v1', [$block, $block]);
    }

    #[DataProvider('invalidLimits')]
    public function testLimitsMustBePositive(int $candidates, int $comparisons, int $fetch, int $report): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FuzzyLimits($candidates, $comparisons, $fetch, $report);
    }

    /** @return iterable<string, array{int, int, int, int}> */
    public static function invalidLimits(): iterable
    {
        yield 'candidate limit' => [0, 1, 1, 1];
        yield 'comparison limit' => [1, 0, 1, 1];
        yield 'fetch batch' => [1, 1, 0, 1];
        yield 'report batch' => [1, 1, 1, 0];
    }
}
