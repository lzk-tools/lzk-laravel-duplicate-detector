<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Metrics;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\SimilarityMetricInterface;

final readonly class NormalizedLevenshtein implements SimilarityMetricInterface
{
    public function __construct(public int $maxCodePoints = 1024)
    {
        if ($maxCodePoints < 1) {
            throw new InvalidArgumentException('Maximum metric length must be positive.');
        }
    }

    public function id(): string
    {
        return 'levenshtein.normalized.codepoints.v1';
    }

    public function similarity(string $left, string $right): float
    {
        $leftPoints = $this->codePoints($left);
        $rightPoints = $this->codePoints($right);
        $leftLength = count($leftPoints);
        $rightLength = count($rightPoints);

        if ($leftLength > $this->maxCodePoints || $rightLength > $this->maxCodePoints) {
            throw new InvalidArgumentException('A metric input exceeds the configured code-point limit.');
        }

        $maximumLength = max($leftLength, $rightLength);
        if ($maximumLength === 0) {
            return 1.0;
        }

        if ($leftLength === 0 || $rightLength === 0) {
            return 0.0;
        }

        if ($leftLength > $rightLength) {
            [$leftPoints, $rightPoints] = [$rightPoints, $leftPoints];
            [$leftLength, $rightLength] = [$rightLength, $leftLength];
        }

        $previous = range(0, $leftLength);
        for ($rightIndex = 1; $rightIndex <= $rightLength; $rightIndex++) {
            $current = [$rightIndex];
            for ($leftIndex = 1; $leftIndex <= $leftLength; $leftIndex++) {
                $substitutionCost = $leftPoints[$leftIndex - 1] === $rightPoints[$rightIndex - 1] ? 0 : 1;
                $current[$leftIndex] = min(
                    $current[$leftIndex - 1] + 1,
                    $previous[$leftIndex] + 1,
                    $previous[$leftIndex - 1] + $substitutionCost,
                );
            }
            $previous = $current;
        }

        $distance = $previous[$leftLength];

        return max(0.0, min(1.0, 1.0 - ($distance / $maximumLength)));
    }

    /** @return list<string> */
    private function codePoints(string $value): array
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException('Metric inputs must be valid UTF-8 strings.');
        }

        $points = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($points === false) {
            throw new InvalidArgumentException('Metric inputs must be valid UTF-8 strings.');
        }

        return $points;
    }
}
