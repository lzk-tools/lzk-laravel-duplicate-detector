<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use Lzk\DuplicateDetector\DTO\RiskClassification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DtoTest extends TestCase
{
    public function testResultsPreserveEvidenceAndIndependentClassification(): void
    {
        $comparison = new ComparisonResult(
            evidence: ['field.exact'],
            contradictions: ['field.conflict'],
        );
        $risk = new RiskClassification('review', ['contradiction.present']);
        $match = new DuplicateMatch(Model::class, '001', '002', $comparison, $risk);
        $report = new DuplicateReport([$match]);

        self::assertSame($match, $report->matches[0]);
        self::assertSame('001', $match->sourceKey);
        self::assertSame(['field.conflict'], $match->comparison->contradictions);
        self::assertSame(['field.exact'], $match->comparison->evidence);
        self::assertSame($risk, $match->classification);
        self::assertNull($comparison->similarityScore);
        self::assertSame([], (new DuplicateReport())->matches);
    }

    public function testScoreIsNotConstrainedToAProbabilityRange(): void
    {
        $result = new ComparisonResult(similarityScore: 2.0, metric: 'example-distance-v1');
        self::assertSame(2.0, $result->similarityScore);
    }

    #[DataProvider('invalidScores')]
    public function testInvalidScoreMetadataIsRejected(?float $score, ?string $metric): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ComparisonResult(similarityScore: $score, metric: $metric);
    }

    /** @return iterable<string, array{?float, ?string}> */
    public static function invalidScores(): iterable
    {
        yield 'missing metric' => [0.5, null];
        yield 'missing score' => [null, 'metric'];
        yield 'empty metric' => [0.5, ' '];
        yield 'infinite' => [INF, 'metric'];
        yield 'not a number' => [NAN, 'metric'];
    }

    public function testEmptyClassificationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RiskClassification(' ');
    }
}
