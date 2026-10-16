<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Fuzzy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

final readonly class FuzzyCandidateFinder
{
    public function __construct(
        private FuzzyRule $rule,
        private SqlBlockingStrategy $blocking,
    ) {
    }

    /**
     * @template TModel of Model
     * @param TModel $source
     * @param Builder<TModel> $scope
     * @return iterable<TModel>
     */
    public function find(Model $source, Builder $scope, FuzzyLimits $limits): iterable
    {
        $model = $scope->getModel();
        $key = $model->getKeyName();
        Identifier::assertColumn($key);
        Identifier::assertColumn($model->getTable());
        $sourceKey = $source->getRawOriginal($key);

        if (
            !$source->exists || (!is_int($sourceKey) && !is_string($sourceKey)) || $sourceKey === ''
            || $source::class !== $model::class || $source->getTable() !== $model->getTable()
            || $source->getKeyName() !== $key || $source->getConnection() !== $model->getConnection()
        ) {
            throw new InvalidArgumentException(
                'Source and fuzzy query must identify the same persisted model and connection.',
            );
        }

        $blockValues = $this->blocking->values($source);
        if ($blockValues === null) {
            return;
        }

        $scoped = (clone $scope)->applyScopes();
        $query = clone $scoped->getQuery();
        $this->assertSupportedQuery($query, $model->getTable());
        if ($query->getConnection() !== $model->getConnection()) {
            throw new InvalidArgumentException('The fuzzy query must use the model connection.');
        }

        $selectedFields = $this->rule->selectedFields();
        $columns = array_values(array_unique([
            $key,
            ...$selectedFields,
            ...$this->blocking->sourceFields(),
            ...$this->blocking->databaseColumns(),
        ]));
        $available = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        if (array_diff($columns, $available) !== []) {
            throw new InvalidArgumentException('A configured fuzzy column does not exist in the model table.');
        }

        $original = clone $query;
        $query->wheres = [];
        $query->setBindings([], 'where');
        $query->addNestedWhereQuery($original);
        foreach ($blockValues as $column => $value) {
            $query->where($model->qualifyColumn($column), '=', $value);
        }
        $query->where($model->qualifyColumn($key), '<>', $sourceKey);
        $query->reorder();
        $query->select(array_map($model->qualifyColumn(...), array_values(array_unique([
            $key,
            ...$selectedFields,
        ]))));

        $lastKey = null;
        $remaining = $limits->maxCandidates;
        while ($remaining > 0) {
            $pageSize = min($limits->fetchBatchSize, $remaining);
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
                throw new InvalidArgumentException('Fuzzy candidate paging requires the selected primary key.');
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
                'Use an unprojected single-table fuzzy query without joins, grouping, limits, locks or callbacks.',
            );
        }
    }
}
