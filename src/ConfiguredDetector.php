<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lzk\DuplicateDetector\Configuration\ConfiguredModelDefinition;
use Lzk\DuplicateDetector\DTO\DuplicateReport;
use LogicException;

/** @template TModel of Model */
final class ConfiguredDetector
{
    /** @var Closure(Builder<TModel>): Builder<TModel>|null */
    private ?Closure $scopeFactory = null;

    /** @param ConfiguredModelDefinition $definition */
    public function __construct(private readonly ConfiguredModelDefinition $definition)
    {
    }

    /**
     * The callback must return the authorized Builder. No unrestricted default exists.
     *
     * @param callable(Builder<TModel>): Builder<TModel> $scope
     * @return self<TModel>
     */
    public function scope(callable $scope): self
    {
        $configured = clone $this;
        $configured->scopeFactory = Closure::fromCallable($scope);

        return $configured;
    }

    /**
     * Exact reports are yielded before fuzzy reports. Results are not deduplicated across engines.
     *
     * @param TModel $source
     * @return iterable<DuplicateReport>
     */
    public function detect(Model $source): iterable
    {
        if ($this->scopeFactory === null) {
            throw new LogicException('An explicit authorized scope is required before detection.');
        }
        $modelClass = $this->definition->modelClass;
        if (!$source instanceof $modelClass) {
            throw new InvalidArgumentException('The source model does not match the configured detector model.');
        }
        /** @var Builder<TModel> $base */
        $base = $modelClass::query();
        $scope = ($this->scopeFactory)($base);
        if (!$scope instanceof Builder || $scope->getModel()::class !== $source::class) {
            throw new InvalidArgumentException('The scope callback must return a Builder for the source model.');
        }

        return $this->run($source, $scope);
    }

    /**
     * @param TModel $source
     * @param Builder<TModel> $scope
     * @return iterable<DuplicateReport>
     */
    private function run(Model $source, Builder $scope): iterable
    {
        if ($this->definition->exactDetector !== null) {
            $exactReports = $this->definition->exactDetector->detect(
                $source,
                $scope,
                $this->definition->exactBatchSize,
                $this->definition->exactMaxCandidates,
            );
            foreach ($this->reports($exactReports) as $report) {
                yield $report;
            }
        }
        if ($this->definition->fuzzyDetector !== null && $this->definition->fuzzyLimits !== null) {
            $fuzzyReports = $this->definition->fuzzyDetector->detect(
                $source,
                $scope,
                $this->definition->fuzzyLimits,
            );
            foreach ($this->reports($fuzzyReports) as $report) {
                yield $report;
            }
        }
    }

    /**
     * @param iterable<DuplicateReport> $reports
     * @return iterable<DuplicateReport>
     */
    private function reports(iterable $reports): iterable
    {
        foreach ($reports as $report) {
            yield $this->definition->classifier?->classifyReport($report) ?? $report;
        }
    }
}
