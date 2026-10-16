<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use Lzk\DuplicateDetector\Contracts\NormalizerInterface;

final readonly class ContradictionRule
{
    /** A contradiction occurs only when both normalized values are present and strictly different. */
    public function __construct(
        public string $id,
        public string $field,
        public NormalizerInterface $normalizer,
    ) {
        Identifier::assertRuleId($id);
        Identifier::assertColumn($field);
    }
}
