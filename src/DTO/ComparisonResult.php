<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\DTO;

use InvalidArgumentException;

final readonly class ComparisonResult
{
    /**
     * A score is a metric value, never a probability or confidence percentage.
     * Its metric identifier must document formula, range, direction and missing-value handling.
     * Evidence and contradictions should contain safe reason codes, not raw personal data.
     *
     * @param list<string> $evidence
     * @param list<string> $matchedFields
     * @param array<string, FieldOutcome> $fieldOutcomes
     * @param list<string> $contradictions
     * @param array<string, FieldComparison> $fieldComparisons
     */
    public function __construct(
        public array $evidence = [],
        public array $contradictions = [],
        public ?float $similarityScore = null,
        public ?string $metric = null,
        public ?string $ruleId = null,
        public array $matchedFields = [],
        public array $fieldOutcomes = [],
        public array $fieldComparisons = [],
    ) {
        if (($similarityScore === null) !== ($metric === null)) {
            throw new InvalidArgumentException('A similarity score and its metric must be supplied together.');
        }

        if ($similarityScore !== null && (!is_finite($similarityScore) || trim((string) $metric) === '')) {
            throw new InvalidArgumentException('The similarity score must be finite and its metric non-empty.');
        }
    }
}
