<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Exact\ExactRule;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExactRuleTest extends TestCase
{
    /**
     * @param array<string, mixed> $fields
     * @param list<string> $matches
     * @param array<string, string> $blocks
     * @param list<string> $contradictions
     */
    #[DataProvider('invalidRules')]
    public function testInvalidConfigurationIsRejected(
        string $id,
        array $fields,
        array $matches,
        array $blocks,
        array $contradictions,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        new ExactRule($id, $fields, $matches, $blocks, $contradictions);
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<string>, array<string, string>, list<string>}> */
    public static function invalidRules(): iterable
    {
        $fields = ['name' => new TextNormalizer()];
        yield 'empty id' => ['', $fields, ['name'], ['name' => 'name_key'], []];
        yield 'empty fields' => ['v1', [], ['name'], ['name' => 'name_key'], []];
        yield 'no matches' => ['v1', $fields, [], ['name' => 'name_key'], []];
        yield 'no blocking' => ['v1', $fields, ['name'], [], []];
        yield 'unknown match field' => ['v1', $fields, ['other'], ['name' => 'name_key'], []];
        yield 'unknown contradiction' => ['v1', $fields, ['name'], ['name' => 'name_key'], ['other']];
        yield 'overlapping roles' => ['v1', $fields, ['name'], ['name' => 'name_key'], ['name']];
        yield 'duplicate match fields' => ['v1', $fields, ['name', 'name'], ['name' => 'name_key'], []];
        yield 'invalid normalizer' => ['v1', ['name' => 'unknown'], ['name'], ['name' => 'name_key'], []];
        yield 'SQL expression' => ['v1', $fields, ['name'], ['name' => 'LOWER(name)'], []];
        yield 'unmatched block' => ['v1', $fields, ['name'], ['other' => 'other_key'], []];
        yield 'unsafe field' => ['v1', ['x.y' => new TextNormalizer()], ['x.y'], ['x.y' => 'key'], []];
    }
}
