<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use InvalidArgumentException;
use Lzk\DuplicateDetector\DTO\FieldOutcome;
use Lzk\DuplicateDetector\Exact\ExactComparator;
use Lzk\DuplicateDetector\Exact\ExactRule;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class ExactComparatorTest extends TestCase
{
    public function testOutcomesAreDistinctAndDoNotContainValues(): void
    {
        $rule = new ExactRule(
            'identity.exact.v1',
            array_fill_keys(['name', 'city', 'phone', 'identity'], new TextNormalizer()),
            ['name'],
            ['name' => 'name_key'],
            ['identity'],
        );
        $result = (new ExactComparator($rule))->compare(
            ['name' => 'private-name', 'city' => 'a', 'phone' => null, 'identity' => 'private-a'],
            ['name' => 'private-name', 'city' => 'b', 'phone' => '123', 'identity' => 'private-b'],
        );

        self::assertSame([
            'name' => FieldOutcome::Match,
            'city' => FieldOutcome::Difference,
            'phone' => FieldOutcome::Missing,
            'identity' => FieldOutcome::Contradiction,
        ], $result->fieldOutcomes);
        self::assertSame(['name'], $result->matchedFields);
        self::assertSame(['field.identity.contradiction'], $result->contradictions);
        self::assertSame('identity.exact.v1', $result->ruleId);
        self::assertNull($result->similarityScore);
        self::assertNull($result->metric);
        self::assertStringNotContainsString('private-', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testBothAbsentAreNotAMatchAndComparisonIsStrict(): void
    {
        $comparator = new ExactComparator(new ExactRule(
            'strict.v1',
            array_fill_keys(['a', 'b', 'c'], new TextNormalizer()),
            ['a'],
            ['a' => 'a_key'],
        ));
        $result = $comparator->compare(['a' => null, 'b' => '', 'c' => '1'], ['a' => null, 'b' => '', 'c' => 1]);
        self::assertSame([], $result->matchedFields);
        self::assertSame(FieldOutcome::Missing, $result->fieldOutcomes['a']);
        self::assertSame(FieldOutcome::Missing, $result->fieldOutcomes['b']);
        self::assertSame(FieldOutcome::Difference, $result->fieldOutcomes['c']);
        self::assertSame([], $result->contradictions);
    }

    public function testAbsentConfiguredKeyIsAnError(): void
    {
        $rule = new ExactRule('one.v1', ['name' => new TextNormalizer()], ['name'], ['name' => 'name_key']);
        $this->expectException(InvalidArgumentException::class);
        (new ExactComparator($rule))->compare([], ['name' => null]);
    }
}
