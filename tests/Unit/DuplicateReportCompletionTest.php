<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use InvalidArgumentException;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DuplicateReportCompletionTest extends TestCase
{
    public function testPartialMetadataIsExplicit(): void
    {
        $report = new DuplicateReport([], false, 10, DuplicateReport::MAX_CANDIDATES_REACHED);

        self::assertFalse($report->searchComplete);
        self::assertSame(10, $report->candidatesExamined);
        self::assertSame(DuplicateReport::MAX_CANDIDATES_REACHED, $report->incompleteReason);
    }

    #[DataProvider('invalidMetadata')]
    public function testInvalidCompletionMetadataIsRejected(
        ?bool $complete,
        ?int $examined,
        ?string $reason,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        new DuplicateReport([], $complete, $examined, $reason);
    }

    /** @return iterable<string, array{?bool, ?int, ?string}> */
    public static function invalidMetadata(): iterable
    {
        yield 'negative count' => [null, -1, null];
        yield 'complete with reason' => [true, 1, DuplicateReport::MAX_CANDIDATES_REACHED];
        yield 'incomplete without reason' => [false, 1, null];
        yield 'status without count' => [false, null, DuplicateReport::MAX_CANDIDATES_REACHED];
    }
}
