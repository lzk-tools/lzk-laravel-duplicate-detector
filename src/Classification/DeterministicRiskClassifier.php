<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Classification;

use Lzk\DuplicateDetector\Contracts\RiskClassifierInterface;
use Lzk\DuplicateDetector\DTO\ComparisonResult;
use Lzk\DuplicateDetector\DTO\FieldOutcome;
use Lzk\DuplicateDetector\DTO\RiskClassification;

final readonly class DeterministicRiskClassifier implements RiskClassifierInterface
{
    public function __construct(private ClassificationPolicy $policy)
    {
    }

    public function classify(ComparisonResult $comparison): RiskClassification
    {
        foreach ($this->policy->rules as $rule) {
            $reasons = $this->match($rule, $comparison);
            if ($reasons === null) {
                continue;
            }

            return new RiskClassification(
                label: $rule->label,
                reasons: $reasons,
                policyId: $this->policy->id,
                ruleId: $rule->id,
                evidence: $this->safeEvidence($comparison),
            );
        }

        return new RiskClassification(
            label: $this->policy->defaultLabel,
            reasons: ['classification.default'],
            policyId: $this->policy->id,
            evidence: $this->safeEvidence($comparison),
        );
    }

    /** @return list<string>|null */
    private function match(ClassificationRule $rule, ComparisonResult $comparison): ?array
    {
        $reasons = ['classification.rule.' . $rule->id];

        if ($rule->comparisonRuleIds !== []) {
            if ($comparison->ruleId === null || !in_array($comparison->ruleId, $rule->comparisonRuleIds, true)) {
                return null;
            }
            $reasons[] = 'comparison-rule.' . $comparison->ruleId;
        }

        foreach ($rule->matchedFields as $field) {
            $matched = in_array($field, $comparison->matchedFields, true)
                || ($comparison->fieldOutcomes[$field] ?? null) === FieldOutcome::Match;
            if (!$matched) {
                return null;
            }
            $reasons[] = 'field.' . $field . '.matched';
        }

        foreach ($rule->minimumSimilarities as $field => $minimum) {
            $fieldComparison = $comparison->fieldComparisons[$field] ?? null;
            if ($fieldComparison === null || $fieldComparison->similarity === null) {
                return null;
            }
            if ($fieldComparison->similarity < $minimum) {
                return null;
            }
            $reasons[] = 'field.' . $field . '.similarity-minimum';
        }

        $hasContradictions = $comparison->contradictions !== [];
        if ($rule->contradictions === ContradictionRequirement::None && $hasContradictions) {
            return null;
        }
        if ($rule->contradictions === ContradictionRequirement::Present && !$hasContradictions) {
            return null;
        }
        if ($rule->contradictions !== ContradictionRequirement::Any) {
            $reasons[] = 'contradictions.' . $rule->contradictions->value;
        }

        $evidence = $this->safeEvidence($comparison);
        if (count($evidence) < $rule->minimumEvidence) {
            return null;
        }
        if ($rule->minimumEvidence > 0) {
            $reasons[] = 'evidence.minimum';
        }
        foreach ($rule->requiredEvidenceCodes as $code) {
            if (!in_array($code, $evidence, true)) {
                return null;
            }
            $reasons[] = 'evidence.' . $code;
        }

        foreach ($rule->presentFields as $field) {
            $outcome = $comparison->fieldOutcomes[$field] ?? null;
            if ($outcome === null || $outcome === FieldOutcome::Missing) {
                return null;
            }
            $reasons[] = 'field.' . $field . '.present';
        }
        foreach ($rule->missingFields as $field) {
            if (($comparison->fieldOutcomes[$field] ?? null) !== FieldOutcome::Missing) {
                return null;
            }
            $reasons[] = 'field.' . $field . '.missing';
        }

        return $reasons;
    }

    /** @return list<string> */
    private function safeEvidence(ComparisonResult $comparison): array
    {
        return array_values(array_unique([
            ...$comparison->evidence,
            ...$comparison->contradictions,
        ]));
    }
}
