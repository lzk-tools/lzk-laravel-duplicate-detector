<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use InvalidArgumentException;

final class Identifier
{
    public static function assertRuleId(string $id): void
    {
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/D', $id) !== 1) {
            throw new InvalidArgumentException('A stable, non-empty rule identifier is required.');
        }
    }

    public static function assertColumn(string $column): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $column) !== 1) {
            throw new InvalidArgumentException('Only simple database column names are supported.');
        }
    }

    private function __construct()
    {
    }
}
