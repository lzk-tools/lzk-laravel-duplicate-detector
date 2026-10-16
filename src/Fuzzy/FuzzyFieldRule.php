<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\NormalizerInterface;
use Lzk\DuplicateDetector\Contracts\SimilarityMetricInterface;

final readonly class FuzzyFieldRule
{
    public function __construct(
        public string $id,
        public string $field,
        public NormalizerInterface $normalizer,
        public SimilarityMetricInterface $metric,
        public float $threshold,
        public bool $required = true,
    ) {
        Identifier::assertRuleId($id);
        Identifier::assertColumn($field);

        if (!is_finite($threshold) || $threshold < 0.0 || $threshold > 1.0) {
            throw new InvalidArgumentException('A fuzzy field threshold must be between zero and one.');
        }
    }
}
