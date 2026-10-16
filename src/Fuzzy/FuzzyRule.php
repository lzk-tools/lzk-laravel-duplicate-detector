<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\NormalizerInterface;

final readonly class FuzzyRule
{
    /**
     * @param non-empty-list<FuzzyFieldRule> $fields
     * @param list<ContradictionRule> $contradictions
     */
    public function __construct(
        public string $id,
        public array $fields,
        public array $contradictions = [],
    ) {
        Identifier::assertRuleId($id);
        if ($fields === [] || !array_is_list($fields)) {
            throw new InvalidArgumentException('A fuzzy rule requires a non-empty field rule list.');
        }

        $ids = [];
        $fieldNames = [];
        $hasRequired = false;
        foreach ($fields as $field) {
            if (!$field instanceof FuzzyFieldRule || isset($ids[$field->id]) || isset($fieldNames[$field->field])) {
                throw new InvalidArgumentException('Fuzzy field rules and fields must be unique.');
            }
            $ids[$field->id] = true;
            $fieldNames[$field->field] = true;
            $hasRequired = $hasRequired || $field->required;
        }

        if (!$hasRequired) {
            throw new InvalidArgumentException('A fuzzy rule requires at least one required field.');
        }

        $contradictionFields = [];
        foreach ($contradictions as $contradiction) {
            if (!$contradiction instanceof ContradictionRule || isset($ids[$contradiction->id])) {
                throw new InvalidArgumentException('All fuzzy and contradiction rule identifiers must be unique.');
            }
            if (isset($fieldNames[$contradiction->field]) || isset($contradictionFields[$contradiction->field])) {
                throw new InvalidArgumentException(
                    'Contradiction fields must be unique and separate from fuzzy comparison fields.',
                );
            }
            $ids[$contradiction->id] = true;
            $contradictionFields[$contradiction->field] = true;
        }
    }

    /** @return list<string> */
    public function selectedFields(): array
    {
        return array_values(array_unique([
            ...array_map(static fn (FuzzyFieldRule $rule): string => $rule->field, $this->fields),
            ...array_map(static fn (ContradictionRule $rule): string => $rule->field, $this->contradictions),
        ]));
    }

    /** @return array<string, string|int|float|bool|null> */
    public function normalize(Model $model): array
    {
        $attributes = $model->getRawOriginal();
        $normalized = [];

        foreach ($this->fields as $rule) {
            $normalized[$rule->field] = $this->normalizeField($attributes, $rule->field, $rule->normalizer);
        }
        foreach ($this->contradictions as $rule) {
            $normalized[$rule->field] = $this->normalizeField($attributes, $rule->field, $rule->normalizer);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function normalizeField(
        array $attributes,
        string $field,
        NormalizerInterface $normalizer,
    ): string|int|float|bool|null {
        if (!array_key_exists($field, $attributes)) {
            throw new InvalidArgumentException('A configured fuzzy field is absent from the model snapshot.');
        }
        $value = $attributes[$field];
        if ($value !== null && !is_scalar($value)) {
            throw new InvalidArgumentException('Configured fuzzy fields must contain scalar values or null.');
        }

        return $normalizer->normalize($value);
    }
}
