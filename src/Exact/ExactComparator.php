<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Exact;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\ComparatorInterface;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\FieldOutcome;

final readonly class ExactComparator implements ComparatorInterface
{
    public function __construct(private ExactRule $rule)
    {
    }

    public function compare(array $left, array $right): ComparisonResult
    {
        $evidence = [];
        $contradictions = [];
        $matched = [];
        $outcomes = [];

        foreach (array_keys($this->rule->fields) as $field) {
            if (!array_key_exists($field, $left) || !array_key_exists($field, $right)) {
                throw new InvalidArgumentException('Comparison requires every configured normalized field.');
            }

            if ($left[$field] === null || $left[$field] === '' || $right[$field] === null || $right[$field] === '') {
                $outcome = FieldOutcome::Missing;
            } elseif ($left[$field] === $right[$field]) {
                $outcome = FieldOutcome::Match;
                $matched[] = $field;
            } elseif (in_array($field, $this->rule->contradictionFields, true)) {
                $outcome = FieldOutcome::Contradiction;
                $contradictions[] = 'field.' . $field . '.contradiction';
            } else {
                $outcome = FieldOutcome::Difference;
            }

            $outcomes[$field] = $outcome;
            $evidence[] = 'field.' . $field . '.' . $outcome->value;
        }

        return new ComparisonResult(
            evidence: $evidence,
            contradictions: $contradictions,
            ruleId: $this->rule->id,
            matchedFields: $matched,
            fieldOutcomes: $outcomes,
        );
    }
}
