<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Exact;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\NormalizerInterface;

/** Provisional explicit rule, not a fluent configuration API. */
final readonly class ExactRule
{
    /**
     * Every match field must agree. Blocking columns contain canonical values
     * produced by the corresponding field normalizer; all blocks are combined with AND.
     *
     * @param array<string, NormalizerInterface> $fields
     * @param list<string> $matchFields
     * @param array<string, string> $blocking field => existing canonical database column
     * @param list<string> $contradictionFields
     */
    public function __construct(
        public string $id,
        public array $fields,
        public array $matchFields,
        public array $blocking,
        public array $contradictionFields = [],
    ) {
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/D', $id) !== 1) {
            throw new InvalidArgumentException('A rule requires a stable, non-empty identifier.');
        }

        if ($fields === [] || $matchFields === [] || $blocking === []) {
            throw new InvalidArgumentException('Fields, match fields and blocking columns are required.');
        }

        foreach ($fields as $field => $normalizer) {
            self::assertColumn($field);
            if (!$normalizer instanceof NormalizerInterface) {
                throw new InvalidArgumentException('Every field requires a normalizer.');
            }
        }

        foreach ([$matchFields, $contradictionFields] as $selection) {
            if (!array_is_list($selection) || count(array_unique($selection)) !== count($selection)) {
                throw new InvalidArgumentException('Field selections must be lists without duplicates.');
            }
            foreach ($selection as $field) {
                if (!array_key_exists($field, $fields)) {
                    throw new InvalidArgumentException('Selected fields must be configured.');
                }
            }
        }

        if (array_intersect($matchFields, $contradictionFields) !== []) {
            throw new InvalidArgumentException('Contradiction fields must be separate from required match fields.');
        }

        foreach ($blocking as $field => $column) {
            self::assertColumn($column);
            if (!in_array($field, $matchFields, true)) {
                throw new InvalidArgumentException('Blocking fields must be required match fields.');
            }
        }
    }

    public static function assertColumn(string $column): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $column) !== 1) {
            throw new InvalidArgumentException('Only simple database column names are supported.');
        }
    }

    /**
     * Read the persisted snapshot; do not invoke accessors, casts or mutate dirty attributes.
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function normalize(Model $model): array
    {
        $attributes = $model->getRawOriginal();
        $normalized = [];

        foreach ($this->fields as $field => $normalizer) {
            if (!array_key_exists($field, $attributes)) {
                throw new InvalidArgumentException('A configured field is absent from the model snapshot.');
            }

            $value = $attributes[$field];
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Configured fields must contain scalar values or null.');
            }
            $normalized[$field] = $normalizer->normalize($value);
        }

        return $normalized;
    }
}
