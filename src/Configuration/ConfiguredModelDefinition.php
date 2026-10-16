<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Configuration;

use Illuminate\Database\Eloquent\Model;
use Lzk\DuplicateDetector\Classification\DuplicateMatchClassifier;
use Lzk\DuplicateDetector\Exact\ExactDuplicateDetector;
use Lzk\DuplicateDetector\Fuzzy\FuzzyDuplicateDetector;
use Lzk\DuplicateDetector\Fuzzy\FuzzyLimits;

final readonly class ConfiguredModelDefinition
{
    /** @param class-string<Model> $modelClass */
    public function __construct(
        public string $modelClass,
        public ?ExactDuplicateDetector $exactDetector,
        public int $exactBatchSize,
        public ?FuzzyDuplicateDetector $fuzzyDetector,
        public ?FuzzyLimits $fuzzyLimits,
        public ?DuplicateMatchClassifier $classifier,
        public int $exactMaxCandidates = ExactDuplicateDetector::DEFAULT_MAX_CANDIDATES,
    ) {
    }
}
