<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Lzk\DuplicateDetector\Classification\DuplicateMatchClassifier;
use Lzk\DuplicateDetector\Contracts\RiskClassifierInterface;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use Lzk\DuplicateDetector\DTO\RiskClassification;
use PHPUnit\Framework\TestCase;

final class ReportClassificationMetadataTest extends TestCase
{
    public function testClassificationPreservesTerminalCompletionMetadata(): void
    {
        $classifier = new class implements RiskClassifierInterface {
            public function classify(ComparisonResult $comparison): RiskClassification
            {
                return new RiskClassification('review');
            }
        };
        $match = new DuplicateMatch(Model::class, 1, 2, new ComparisonResult());
        $report = new DuplicateReport(
            [$match],
            false,
            10,
            DuplicateReport::MAX_CANDIDATES_REACHED,
        );

        $classified = (new DuplicateMatchClassifier($classifier))->classifyReport($report);

        self::assertSame(false, $classified->searchComplete);
        self::assertSame(10, $classified->candidatesExamined);
        self::assertSame(DuplicateReport::MAX_CANDIDATES_REACHED, $classified->incompleteReason);
        self::assertSame('review', $classified->matches[0]->classification?->label);
    }
}
