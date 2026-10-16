<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class SqlBlockingStrategy
{
    /** @param non-empty-list<SqlBlock> $blocks */
    public function __construct(public string $id, public array $blocks)
    {
        Identifier::assertRuleId($id);
        if ($blocks === [] || !array_is_list($blocks)) {
            throw new InvalidArgumentException('A SQL blocking strategy requires a non-empty block list.');
        }

        $columns = [];
        foreach ($blocks as $block) {
            if (!$block instanceof SqlBlock || isset($columns[$block->databaseColumn])) {
                throw new InvalidArgumentException('SQL blocks must be unique SqlBlock instances.');
            }
            $columns[$block->databaseColumn] = true;
        }
    }

    /** @return array<string, string|int|float|bool>|null column => normalized value */
    public function values(Model $source): ?array
    {
        $attributes = $source->getRawOriginal();
        $values = [];

        foreach ($this->blocks as $block) {
            if (!array_key_exists($block->sourceField, $attributes)) {
                throw new InvalidArgumentException('A blocking source field is absent from the model snapshot.');
            }

            $value = $attributes[$block->sourceField];
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Blocking fields must contain scalar values or null.');
            }
            $normalized = $block->normalizer->normalize($value);
            if ($normalized === null || $normalized === '') {
                return null;
            }
            $values[$block->databaseColumn] = $normalized;
        }

        return $values;
    }

    /** @return list<string> */
    public function sourceFields(): array
    {
        return array_values(array_unique(array_map(
            static fn (SqlBlock $block): string => $block->sourceField,
            $this->blocks,
        )));
    }

    /** @return list<string> */
    public function databaseColumns(): array
    {
        return array_values(array_unique(array_map(
            static fn (SqlBlock $block): string => $block->databaseColumn,
            $this->blocks,
        )));
    }
}
