<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Contracts;

interface SimilarityMetricInterface
{
    /** Stable identifier including the metric definition version. */
    public function id(): string;

    /** Return a finite similarity in the inclusive range [0, 1]. */
    public function similarity(string $left, string $right): float;
}
