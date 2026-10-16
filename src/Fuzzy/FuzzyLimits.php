<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use InvalidArgumentException;

final readonly class FuzzyLimits
{
    public function __construct(
        public int $maxCandidates,
        public int $maxComparisons,
        public int $fetchBatchSize = 500,
        public int $reportBatchSize = 500,
    ) {
        if (
            $maxCandidates < 1 || $maxComparisons < 1
            || $fetchBatchSize < 1 || $reportBatchSize < 1
        ) {
            throw new InvalidArgumentException('All fuzzy detection limits must be positive.');
        }
    }
}
