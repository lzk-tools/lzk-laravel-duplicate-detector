<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Classification;

use InvalidArgumentException;

final readonly class ClassificationRule
{
    /**
     * @param list<string> $comparisonRuleIds
     * @param list<string> $matchedFields
     * @param array<string, float> $minimumSimilarities
     * @param list<string> $requiredEvidenceCodes
     * @param list<string> $presentFields
     * @param list<string> $missingFields
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $comparisonRuleIds = [],
        public array $matchedFields = [],
        public array $minimumSimilarities = [],
        public ContradictionRequirement $contradictions = ContradictionRequirement::Any,
        public int $minimumEvidence = 0,
        public array $requiredEvidenceCodes = [],
        public array $presentFields = [],
        public array $missingFields = [],
    ) {
        self::assertIdentifier($id, 'Classification rule identifier');
        self::assertIdentifier($label, 'Classification label');
        self::assertUniqueIdentifiers($comparisonRuleIds, 'Comparison rule identifiers');
        self::assertUniqueIdentifiers($matchedFields, 'Matched fields');
        self::assertUniqueIdentifiers($requiredEvidenceCodes, 'Evidence codes');
        self::assertUniqueIdentifiers($presentFields, 'Present fields');
        self::assertUniqueIdentifiers($missingFields, 'Missing fields');

        if ($minimumEvidence < 0) {
            throw new InvalidArgumentException('Minimum evidence must be zero or greater.');
        }
        if (array_intersect($presentFields, $missingFields) !== []) {
            throw new InvalidArgumentException('A field cannot be required as both present and missing.');
        }
        foreach ($minimumSimilarities as $field => $threshold) {
            self::assertIdentifier($field, 'Similarity field');
            if (!is_float($threshold) || !is_finite($threshold) || $threshold < 0.0 || $threshold > 1.0) {
                throw new InvalidArgumentException('Minimum similarities must be finite floats between zero and one.');
            }
        }
    }

    private static function assertIdentifier(string $value, string $description): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException($description . ' must not be empty.');
        }
    }

    /** @param list<string> $values */
    private static function assertUniqueIdentifiers(array $values, string $description): void
    {
        if (!array_is_list($values) || count($values) !== count(array_unique($values))) {
            throw new InvalidArgumentException($description . ' must be a list of unique values.');
        }
        foreach ($values as $value) {
            self::assertIdentifier($value, $description);
        }
    }
}
