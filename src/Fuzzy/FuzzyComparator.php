<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\ComparatorInterface;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\FieldComparison;
use Lzk\DuplicateDetector\DTO\FieldOutcome;

final readonly class FuzzyComparator implements ComparatorInterface
{
    public function __construct(private FuzzyRule $rule)
    {
    }

    public function compare(array $left, array $right): ComparisonResult
    {
        $evidence = [];
        $contradictions = [];
        $matched = [];
        $outcomes = [];
        $fieldComparisons = [];

        foreach ($this->rule->fields as $fieldRule) {
            $this->assertPresent($left, $fieldRule->field);
            $this->assertPresent($right, $fieldRule->field);
            $leftValue = $left[$fieldRule->field];
            $rightValue = $right[$fieldRule->field];

            if ($this->isMissing($leftValue) || $this->isMissing($rightValue)) {
                $similarity = null;
                $outcome = FieldOutcome::Missing;
            } else {
                if (!is_string($leftValue) || !is_string($rightValue)) {
                    throw new InvalidArgumentException('Fuzzy metrics require normalized string values.');
                }
                $similarity = $fieldRule->metric->similarity($leftValue, $rightValue);
                $outcome = $similarity >= $fieldRule->threshold
                    ? FieldOutcome::Match
                    : FieldOutcome::Difference;
            }

            if ($outcome === FieldOutcome::Match) {
                $matched[] = $fieldRule->field;
            }
            $outcomes[$fieldRule->field] = $outcome;
            $evidence[] = 'similarity.' . $fieldRule->id . '.' . $outcome->value;
            $fieldComparisons[$fieldRule->field] = new FieldComparison(
                ruleId: $fieldRule->id,
                field: $fieldRule->field,
                metric: $fieldRule->metric->id(),
                threshold: $fieldRule->threshold,
                required: $fieldRule->required,
                similarity: $similarity,
                outcome: $outcome,
            );
        }

        foreach ($this->rule->contradictions as $rule) {
            $this->assertPresent($left, $rule->field);
            $this->assertPresent($right, $rule->field);
            $leftValue = $left[$rule->field];
            $rightValue = $right[$rule->field];

            $bothPresent = !$this->isMissing($leftValue) && !$this->isMissing($rightValue);
            if ($bothPresent && $leftValue !== $rightValue) {
                $outcomes[$rule->field] = FieldOutcome::Contradiction;
                $contradictions[] = 'contradiction.' . $rule->id;
                $evidence[] = 'contradiction.' . $rule->id . '.present';
            } elseif ($this->isMissing($leftValue) || $this->isMissing($rightValue)) {
                $outcomes[$rule->field] = FieldOutcome::Missing;
                $evidence[] = 'contradiction.' . $rule->id . '.missing';
            } else {
                $outcomes[$rule->field] = FieldOutcome::Match;
                $matched[] = $rule->field;
                $evidence[] = 'contradiction.' . $rule->id . '.absent';
            }
        }

        return new ComparisonResult(
            evidence: $evidence,
            contradictions: $contradictions,
            ruleId: $this->rule->id,
            matchedFields: array_values(array_unique($matched)),
            fieldOutcomes: $outcomes,
            fieldComparisons: $fieldComparisons,
        );
    }

    public function qualifies(ComparisonResult $comparison): bool
    {
        foreach ($this->rule->fields as $fieldRule) {
            $outcome = $comparison->fieldOutcomes[$fieldRule->field] ?? null;

            if ($fieldRule->required && $outcome !== FieldOutcome::Match) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string|int|float|bool|null> $values */
    private function assertPresent(array $values, string $field): void
    {
        if (!array_key_exists($field, $values)) {
            throw new InvalidArgumentException('Comparison requires every configured fuzzy field.');
        }
    }

    private function isMissing(string|int|float|bool|null $value): bool
    {
        return $value === null || $value === '';
    }
}
