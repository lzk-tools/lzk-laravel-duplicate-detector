<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\DTO;

use InvalidArgumentException;

final readonly class FieldComparison
{
    public function __construct(
        public string $ruleId,
        public string $field,
        public string $metric,
        public float $threshold,
        public bool $required,
        public ?float $similarity,
        public FieldOutcome $outcome,
    ) {
        if ($ruleId === '' || $field === '' || $metric === '') {
            throw new InvalidArgumentException('Field comparison identifiers must not be empty.');
        }

        if (!is_finite($threshold) || $threshold < 0.0 || $threshold > 1.0) {
            throw new InvalidArgumentException('A field comparison threshold must be between zero and one.');
        }

        if ($similarity !== null && (!is_finite($similarity) || $similarity < 0.0 || $similarity > 1.0)) {
            throw new InvalidArgumentException('A field similarity must be null or between zero and one.');
        }

        if (($similarity === null) !== ($outcome === FieldOutcome::Missing)) {
            throw new InvalidArgumentException('Only a missing field outcome may omit its similarity.');
        }
    }
}
