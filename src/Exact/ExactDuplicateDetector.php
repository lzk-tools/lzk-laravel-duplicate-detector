<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Exact;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\DTO\DuplicateMatch;
use Lzk\DuplicateDetector\DTO\DuplicateReport;

final readonly class ExactDuplicateDetector
{
    public const DEFAULT_MAX_CANDIDATES = 1000;

    public function __construct(private ExactRule $rule)
    {
    }

    /**
     * Yield bounded reports of exact matches, never an accumulated full scan.
     * The final report carries completion metadata. Reaching the candidate limit is
     * conservatively partial, without querying for an additional candidate.
     *
     * @template TModel of Model
     * @param TModel $source
     * @param Builder<TModel> $scope
     * @param int $batchSize Validated positive at runtime.
     * @param int $maxCandidates Validated positive at runtime.
     * @return iterable<DuplicateReport>
     */
    public function detect(
        Model $source,
        Builder $scope,
        int $batchSize = 500,
        int $maxCandidates = self::DEFAULT_MAX_CANDIDATES,
    ): iterable {
        if ($maxCandidates < 1) {
            throw new InvalidArgumentException('Maximum exact candidates must be positive.');
        }

        $finder = new ExactCandidateFinder($this->rule, $maxCandidates);
        $comparator = new ExactComparator($this->rule);
        $sourceValues = $this->rule->normalize($source);
        $matches = [];
        $pending = [];
        $candidatesExamined = 0;

        foreach ($finder->find($source, $scope, $batchSize) as $candidate) {
            $candidatesExamined++;
            $comparison = $comparator->compare($sourceValues, $this->rule->normalize($candidate));
            if (array_diff($this->rule->matchFields, $comparison->matchedFields) !== []) {
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
                $source::class,
                $sourceKey,
                $candidateKey,
                $comparison,
            );

            if (count($matches) === $batchSize) {
                if ($pending !== []) {
                    yield new DuplicateReport($pending);
                }
                $pending = $matches;
                $matches = [];
            }
        }

        $complete = $candidatesExamined < $maxCandidates;
        $reason = $complete ? null : DuplicateReport::MAX_CANDIDATES_REACHED;

        if ($matches !== []) {
            if ($pending !== []) {
                yield new DuplicateReport($pending);
            }
            yield new DuplicateReport($matches, $complete, $candidatesExamined, $reason);

            return;
        }

        if ($pending !== []) {
            yield new DuplicateReport($pending, $complete, $candidatesExamined, $reason);

            return;
        }

        if (!$complete) {
            yield new DuplicateReport([], false, $candidatesExamined, $reason);
        }
    }
}
