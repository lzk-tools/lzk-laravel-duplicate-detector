<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Contracts;

use Lzk\DuplicateDetector\DTO\ComparisonResult;

interface ComparatorInterface
{
    /**
     * Compare normalized fields using a documented exact or approximate strategy.
     * Missing evidence is not agreement. Record contradictions independently of similarity.
     *
     * @param array<string, string|int|float|bool|null> $left
     * @param array<string, string|int|float|bool|null> $right
     */
    public function compare(array $left, array $right): ComparisonResult;
}
