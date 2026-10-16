<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Contracts;

use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\RiskClassification;

interface RiskClassifierInterface
{
    /** Classify evidence using a documented policy. Never authorize a merge. */
    public function classify(ComparisonResult $comparison): RiskClassification;
}
