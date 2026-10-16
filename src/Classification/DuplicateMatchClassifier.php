<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Classification;

use Lzk\DuplicateDetector\Contracts\RiskClassifierInterface;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;

final readonly class DuplicateMatchClassifier
{
    public function __construct(private RiskClassifierInterface $classifier)
    {
    }

    public function classify(DuplicateMatch $match): DuplicateMatch
    {
        return new DuplicateMatch(
            modelClass: $match->modelClass,
            sourceKey: $match->sourceKey,
            candidateKey: $match->candidateKey,
            comparison: $match->comparison,
            classification: $this->classifier->classify($match->comparison),
        );
    }

    public function classifyReport(DuplicateReport $report): DuplicateReport
    {
        return new DuplicateReport(
            matches: array_map($this->classify(...), $report->matches),
            searchComplete: $report->searchComplete,
            candidatesExamined: $report->candidatesExamined,
            incompleteReason: $report->incompleteReason,
        );
    }
}
