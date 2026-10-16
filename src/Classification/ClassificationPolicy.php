<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Classification;

use InvalidArgumentException;

final readonly class ClassificationPolicy
{
    /** @param list<ClassificationRule> $rules */
    public function __construct(
        public string $id,
        public array $rules,
        public string $defaultLabel,
    ) {
        if (trim($id) === '' || trim($defaultLabel) === '') {
            throw new InvalidArgumentException('Policy identifier and default label must not be empty.');
        }
        if (!array_is_list($rules)) {
            throw new InvalidArgumentException('Classification rules must be a list.');
        }

        $ids = [];
        foreach ($rules as $rule) {
            if (!$rule instanceof ClassificationRule || isset($ids[$rule->id])) {
                throw new InvalidArgumentException('Classification rule identifiers must be unique.');
            }
            $ids[$rule->id] = true;
        }
    }
}
