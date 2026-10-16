<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;

final readonly class FuzzyDuplicateDetector
{
    public function __construct(
        private FuzzyRule $rule,
        private SqlBlockingStrategy $blocking,
    ) {
    }

    /**
     * @template TModel of Model
     * @param TModel $source
     * @param Builder<TModel> $scope
     * @return iterable<DuplicateReport>
     */
    public function detect(Model $source, Builder $scope, FuzzyLimits $limits): iterable
    {
        $finder = new FuzzyCandidateFinder($this->rule, $this->blocking);
        $comparator = new FuzzyComparator($this->rule);
        $sourceValues = $this->rule->normalize($source);
        $matches = [];
        $comparisons = 0;

        foreach ($finder->find($source, $scope, $limits) as $candidate) {
            if ($comparisons >= $limits->maxComparisons) {
                break;
            }
            $comparisons++;
            $comparison = $comparator->compare($sourceValues, $this->rule->normalize($candidate));
            if (!$comparator->qualifies($comparison)) {
                continue;
            }

            $sourceKey = $source->getRawOriginal($source->getKeyName());
            $candidateKey = $candidate->getRawOriginal($candidate->getKeyName());
            if (
                (!is_int($sourceKey) && !is_string($sourceKey))
                || (!is_int($candidateKey) && !is_string($candidateKey))
            ) {
                throw new InvalidArgumentException('Only scalar string or integer record keys are supported.');
            }

            $matches[] = new DuplicateMatch(
                modelClass: $source::class,
                sourceKey: $sourceKey,
                candidateKey: $candidateKey,
                comparison: $comparison,
            );

            if (count($matches) === $limits->reportBatchSize) {
                yield new DuplicateReport($matches);
                $matches = [];
            }
        }

        if ($matches !== []) {
            yield new DuplicateReport($matches);
        }
    }
}
