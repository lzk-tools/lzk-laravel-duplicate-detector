<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Configuration;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\Classification\ClassificationPolicy;
use Lzk\DuplicateDetector\Classification\ClassificationRule;
use Lzk\DuplicateDetector\Classification\ContradictionRequirement;
use Lzk\DuplicateDetector\Classification\DeterministicRiskClassifier;
use Lzk\DuplicateDetector\Classification\DuplicateMatchClassifier;
use Lzk\DuplicateDetector\Contracts\NormalizerInterface;
use Lzk\DuplicateDetector\Exact\ExactDuplicateDetector;
use Lzk\DuplicateDetector\Exact\ExactRule;
use Lzk\DuplicateDetector\Fuzzy\ContradictionRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyDuplicateDetector;
use Lzk\DuplicateDetector\Fuzzy\FuzzyFieldRule;
use Lzk\DuplicateDetector\Fuzzy\FuzzyLimits;
use Lzk\DuplicateDetector\Fuzzy\FuzzyRule;
use Lzk\DuplicateDetector\Fuzzy\SqlBlock;
use Lzk\DuplicateDetector\Fuzzy\SqlBlockingStrategy;
use Lzk\DuplicateDetector\Metrics\NormalizedLevenshtein;
use Lzk\DuplicateDetector\Normalizers\EmailNormalizer;
use Lzk\DuplicateDetector\Normalizers\PhoneNormalizer;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;

final readonly class DetectorConfigResolver
{
    public function __construct(private Repository $config)
    {
    }

    /** @param class-string<Model> $modelClass */
    public function resolve(string $modelClass): ConfiguredModelDefinition
    {
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException('Configured detector models must extend Eloquent Model.');
        }

        $models = $this->config->get('duplicate-detector.models', []);
        if (!is_array($models) || !array_key_exists($modelClass, $models) || !is_array($models[$modelClass])) {
            throw new InvalidArgumentException('The requested model is not configured for duplicate detection.');
        }
        /** @var array<string, mixed> $definition */
        $definition = $models[$modelClass];
        $exact = $this->exact($definition['exact'] ?? null);
        $fuzzy = $this->fuzzy($definition['fuzzy'] ?? null);
        if ($exact === null && $fuzzy === null) {
            throw new InvalidArgumentException('A configured model requires an exact or fuzzy engine.');
        }

        $exactConfig = is_array($definition['exact'] ?? null) ? $definition['exact'] : [];
        $batchSize = $this->positiveInt($exactConfig['batch_size'] ?? 500, 'Exact batch size');
        $maxCandidates = $this->positiveInt(
            array_key_exists('max_candidates', $exactConfig)
                ? $exactConfig['max_candidates']
                : ExactDuplicateDetector::DEFAULT_MAX_CANDIDATES,
            'Maximum exact candidates',
        );

        return new ConfiguredModelDefinition(
            modelClass: $modelClass,
            exactDetector: $exact,
            exactBatchSize: $batchSize,
            exactMaxCandidates: $maxCandidates,
            fuzzyDetector: $fuzzy['detector'] ?? null,
            fuzzyLimits: $fuzzy['limits'] ?? null,
            classifier: $this->classifier($definition['classification'] ?? null),
        );
    }

    /** @return ExactDuplicateDetector|null */
    private function exact(mixed $value): ?ExactDuplicateDetector
    {
        if ($value === null) {
            return null;
        }
        $config = $this->section($value, 'Exact configuration');
        $fields = [];
        foreach ($this->section($config['fields'] ?? null, 'Exact fields') as $field => $normalizer) {
            if (!is_string($field) || !is_string($normalizer)) {
                throw new InvalidArgumentException('Exact fields map field names to normalizer aliases.');
            }
            $fields[$field] = $this->normalizer($normalizer);
        }

        return new ExactDuplicateDetector(new ExactRule(
            id: $this->string($config['id'] ?? null, 'Exact rule identifier'),
            fields: $fields,
            matchFields: $this->stringList($config['match_fields'] ?? null, 'Exact match fields'),
            blocking: $this->stringMap($config['blocking'] ?? null, 'Exact blocking'),
            contradictionFields: $this->stringList(
                $config['contradiction_fields'] ?? [],
                'Exact contradiction fields',
            ),
        ));
    }

    /** @return array{detector: FuzzyDuplicateDetector, limits: FuzzyLimits}|null */
    private function fuzzy(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $config = $this->section($value, 'Fuzzy configuration');
        $fields = [];
        foreach ($this->section($config['fields'] ?? null, 'Fuzzy fields') as $field => $fieldValue) {
            if (!is_string($field)) {
                throw new InvalidArgumentException('Fuzzy field names must be strings.');
            }
            $fieldConfig = $this->section($fieldValue, 'Fuzzy field configuration');
            $metric = $this->string($fieldConfig['metric'] ?? null, 'Fuzzy metric');
            if ($metric !== 'normalized_levenshtein') {
                throw new InvalidArgumentException('Unsupported fuzzy metric alias.');
            }
            $fields[] = new FuzzyFieldRule(
                id: $this->string($fieldConfig['id'] ?? null, 'Fuzzy field rule identifier'),
                field: $field,
                normalizer: $this->normalizer($this->string(
                    $fieldConfig['normalizer'] ?? null,
                    'Fuzzy normalizer',
                )),
                metric: new NormalizedLevenshtein($this->positiveInt(
                    $fieldConfig['max_code_points'] ?? 1024,
                    'Maximum metric code points',
                )),
                threshold: $this->unitFloat($fieldConfig['threshold'] ?? null, 'Fuzzy threshold'),
                required: $this->boolean($fieldConfig['required'] ?? true, 'Fuzzy required flag'),
            );
        }
        if ($fields === []) {
            throw new InvalidArgumentException('Fuzzy configuration requires at least one field.');
        }
        /** @var non-empty-list<FuzzyFieldRule> $fields */

        $contradictions = [];
        foreach ($this->section($config['contradictions'] ?? [], 'Fuzzy contradictions') as $field => $ruleValue) {
            if (!is_string($field)) {
                throw new InvalidArgumentException('Contradiction field names must be strings.');
            }
            $ruleConfig = $this->section($ruleValue, 'Contradiction configuration');
            $contradictions[] = new ContradictionRule(
                $this->string($ruleConfig['id'] ?? null, 'Contradiction rule identifier'),
                $field,
                $this->normalizer($this->string(
                    $ruleConfig['normalizer'] ?? null,
                    'Contradiction normalizer',
                )),
            );
        }
        $rule = new FuzzyRule(
            $this->string($config['id'] ?? null, 'Fuzzy rule identifier'),
            $fields,
            $contradictions,
        );
        $blockingConfig = $this->section($config['blocking'] ?? null, 'Fuzzy blocking');
        $blocks = [];
        $blockValues = $blockingConfig['blocks'] ?? null;
        if (!is_array($blockValues) || !array_is_list($blockValues)) {
            throw new InvalidArgumentException('Fuzzy blocks must be a list.');
        }
        foreach ($blockValues as $blockValue) {
            $block = $this->section($blockValue, 'Fuzzy block');
            $blocks[] = new SqlBlock(
                $this->string($block['source_field'] ?? null, 'Block source field'),
                $this->string($block['column'] ?? null, 'Block database column'),
                $this->normalizer($this->string($block['normalizer'] ?? null, 'Block normalizer')),
            );
        }
        if ($blocks === []) {
            throw new InvalidArgumentException('Fuzzy blocking requires at least one block.');
        }
        /** @var non-empty-list<SqlBlock> $blocks */
        $blocking = new SqlBlockingStrategy(
            $this->string($blockingConfig['id'] ?? null, 'Blocking strategy identifier'),
            $blocks,
        );
        $limits = $this->section($config['limits'] ?? null, 'Fuzzy limits');

        return [
            'detector' => new FuzzyDuplicateDetector($rule, $blocking),
            'limits' => new FuzzyLimits(
                $this->positiveInt($limits['max_candidates'] ?? null, 'Maximum candidates'),
                $this->positiveInt($limits['max_comparisons'] ?? null, 'Maximum comparisons'),
                $this->positiveInt($limits['fetch_batch_size'] ?? null, 'Fetch batch size'),
                $this->positiveInt($limits['report_batch_size'] ?? null, 'Report batch size'),
            ),
        ];
    }

    private function classifier(mixed $value): ?DuplicateMatchClassifier
    {
        if ($value === null) {
            return null;
        }
        $config = $this->section($value, 'Classification configuration');
        $ruleValues = $config['rules'] ?? null;
        if (!is_array($ruleValues) || !array_is_list($ruleValues)) {
            throw new InvalidArgumentException('Classification rules must be a list.');
        }
        $rules = [];
        foreach ($ruleValues as $ruleValue) {
            $rule = $this->section($ruleValue, 'Classification rule');
            $contradictions = $this->string(
                $rule['contradictions'] ?? ContradictionRequirement::Any->value,
                'Contradiction requirement',
            );
            $contradictionRequirement = ContradictionRequirement::tryFrom($contradictions);
            if ($contradictionRequirement === null) {
                throw new InvalidArgumentException('Unknown contradiction requirement.');
            }
            $rules[] = new ClassificationRule(
                id: $this->string($rule['id'] ?? null, 'Classification rule identifier'),
                label: $this->string($rule['label'] ?? null, 'Classification label'),
                comparisonRuleIds: $this->stringList(
                    $rule['comparison_rule_ids'] ?? [],
                    'Comparison rule identifiers',
                ),
                matchedFields: $this->stringList($rule['matched_fields'] ?? [], 'Matched fields'),
                minimumSimilarities: $this->floatMap(
                    $rule['minimum_similarities'] ?? [],
                    'Minimum similarities',
                ),
                contradictions: $contradictionRequirement,
                minimumEvidence: $this->nonNegativeInt($rule['minimum_evidence'] ?? 0, 'Minimum evidence'),
                requiredEvidenceCodes: $this->stringList(
                    $rule['required_evidence_codes'] ?? [],
                    'Required evidence codes',
                ),
                presentFields: $this->stringList($rule['present_fields'] ?? [], 'Present fields'),
                missingFields: $this->stringList($rule['missing_fields'] ?? [], 'Missing fields'),
            );
        }

        return new DuplicateMatchClassifier(new DeterministicRiskClassifier(new ClassificationPolicy(
            $this->string($config['id'] ?? null, 'Classification policy identifier'),
            $rules,
            $this->string($config['default_label'] ?? null, 'Default classification label'),
        )));
    }

    private function normalizer(string $alias): NormalizerInterface
    {
        return match ($alias) {
            'email' => new EmailNormalizer(),
            'text' => new TextNormalizer(),
            'phone' => new PhoneNormalizer(),
            default => throw new InvalidArgumentException('Unsupported normalizer alias.'),
        };
    }

    /** @return array<string, mixed> */
    private function section(mixed $value, string $description): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException($description . ' must be an array.');
        }

        return $value;
    }

    private function string(mixed $value, string $description): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($description . ' must be a non-empty string.');
        }

        return $value;
    }

    /** @return list<string> */
    private function stringList(mixed $value, string $description): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException($description . ' must be a list.');
        }
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException($description . ' must contain non-empty strings.');
            }
        }

        return $value;
    }

    /** @return array<string, string> */
    private function stringMap(mixed $value, string $description): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException($description . ' must be a map.');
        }
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_string($item) || trim($key) === '' || trim($item) === '') {
                throw new InvalidArgumentException($description . ' must map non-empty strings.');
            }
        }

        return $value;
    }

    /** @return array<string, float> */
    private function floatMap(mixed $value, string $description): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException($description . ' must be a map.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new InvalidArgumentException($description . ' must use string fields.');
            }
            $result[$key] = $this->unitFloat($item, $description);
        }

        return $result;
    }

    private function unitFloat(mixed $value, string $description): float
    {
        if (!is_float($value) && !is_int($value)) {
            throw new InvalidArgumentException($description . ' must be numeric.');
        }
        $result = (float) $value;
        if (!is_finite($result) || $result < 0.0 || $result > 1.0) {
            throw new InvalidArgumentException($description . ' must be between zero and one.');
        }

        return $result;
    }

    private function positiveInt(mixed $value, string $description): int
    {
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException($description . ' must be a positive integer.');
        }

        return $value;
    }

    private function nonNegativeInt(mixed $value, string $description): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException($description . ' must be zero or greater.');
        }

        return $value;
    }

    private function boolean(mixed $value, string $description): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($description . ' must be boolean.');
        }

        return $value;
    }
}
