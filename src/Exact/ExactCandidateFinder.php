<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Exact;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\CandidateFinderInterface;

final readonly class ExactCandidateFinder implements CandidateFinderInterface
{
    public function __construct(
        private ExactRule $rule,
        private int $maxCandidates = ExactDuplicateDetector::DEFAULT_MAX_CANDIDATES,
    ) {
        if ($maxCandidates < 1) {
            throw new InvalidArgumentException('Maximum exact candidates must be positive.');
        }
    }

    /**
     * @template TModel of Model
     * @param TModel $source
     * @param Builder<TModel> $scope
     * @param int $batchSize Validated positive at runtime.
     * @return iterable<TModel>
     */
    public function find(Model $source, Builder $scope, int $batchSize): iterable
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be positive.');
        }

        $model = $scope->getModel();
        $key = $model->getKeyName();
        ExactRule::assertColumn($key);
        ExactRule::assertColumn($model->getTable());
        $sourceKey = $source->getRawOriginal($key);

        if (
            !$source->exists || (!is_int($sourceKey) && !is_string($sourceKey)) || $sourceKey === ''
            || $source::class !== $model::class || $source->getTable() !== $model->getTable()
            || $source->getKeyName() !== $key || $source->getConnection() !== $model->getConnection()
        ) {
            throw new InvalidArgumentException(
                'Source and query must identify the same persisted model and connection.',
            );
        }

        $normalized = $this->rule->normalize($source);
        $scoped = (clone $scope)->applyScopes();
        $query = clone $scoped->getQuery();
        $this->assertSupportedQuery($query, $model->getTable());
        if ($query->getConnection() !== $model->getConnection()) {
            throw new InvalidArgumentException('The query must use the model connection.');
        }

        $blocks = [];
        foreach ($this->rule->blocking as $field => $column) {
            $value = $normalized[$field];
            if ($value === null || $value === '') {
                return;
            }
            $blocks[$column] = $value;
        }

        $columns = array_values(array_unique([
            $key,
            ...array_keys($this->rule->fields),
            ...array_keys($blocks),
        ]));
        $available = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        if (array_diff($columns, $available) !== []) {
            throw new InvalidArgumentException('A configured column does not exist in the model table.');
        }

        // Group the original predicate before adding ANDs: top-level OR must not bypass blocks.
        $original = clone $query;
        $query->wheres = [];
        $query->setBindings([], 'where');
        $query->addNestedWhereQuery($original);

        foreach ($blocks as $column => $value) {
            $query->where($model->qualifyColumn($column), '=', $value);
        }

        $query->where($model->qualifyColumn($key), '<>', $sourceKey);
        $query->reorder();
        $query->select(array_map($model->qualifyColumn(...), array_values(array_unique([
            $key,
            ...array_keys($this->rule->fields),
        ]))));

        $lastKey = null;
        $remaining = $this->maxCandidates;
        while ($remaining > 0) {
            $pageSize = min($batchSize, $remaining);
            $pageQuery = clone $query;
            if ($lastKey !== null) {
                $pageQuery->where($model->qualifyColumn($key), '>', $lastKey);
            }
            $page = $pageQuery
                ->orderBy($model->qualifyColumn($key))
                ->limit($pageSize)
                ->get();

            foreach ($page as $row) {
                $candidate = clone $model;
                $candidate->setRawAttributes((array) $row, true);
                $candidate->setRelations([]);
                $candidate->exists = true;
                $candidate->wasRecentlyCreated = false;
                yield $candidate;
                $remaining--;
            }

            if ($page->count() < $pageSize || $remaining === 0) {
                return;
            }

            $last = $page->last();
            $lastKey = is_object($last) ? data_get($last, $key) : null;
            if (!is_int($lastKey) && !is_string($lastKey)) {
                throw new InvalidArgumentException('Exact candidate paging requires the selected primary key.');
            }
        }
    }

    private function assertSupportedQuery(QueryBuilder $query, string $table): void
    {
        if (
            $query->from !== $table || $query->joins !== null || $query->unions !== null
            || $query->groups !== null || $query->havings !== null || $query->aggregate !== null
            || $query->distinct !== false || $query->limit !== null || $query->offset !== null
            || $query->groupLimit !== null || $query->lock !== null || $query->columns !== null
            || $query->beforeQueryCallbacks !== []
        ) {
            throw new InvalidArgumentException(
                'Use an unprojected single-table query without joins, grouping, limits, locks or query callbacks.',
            );
        }
    }
}
