<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use Lzk\DuplicateDetector\Contracts\NormalizerInterface;

final readonly class SqlBlock
{
    public function __construct(
        public string $sourceField,
        public string $databaseColumn,
        public NormalizerInterface $normalizer,
    ) {
        Identifier::assertColumn($sourceField);
        Identifier::assertColumn($databaseColumn);
    }
}
