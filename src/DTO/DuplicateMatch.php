<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\DTO;

use Illuminate\Database\Eloquent\Model;

final readonly class DuplicateMatch
{
    /** @param class-string<Model> $modelClass */
    public function __construct(
        public string $modelClass,
        public string|int $sourceKey,
        public string|int $candidateKey,
        public ComparisonResult $comparison,
        public ?RiskClassification $classification = null,
    ) {
    }
}
