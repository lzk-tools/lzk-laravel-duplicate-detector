<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface CandidateFinderInterface
{
    /**
     * Yield candidates lazily using bounded fetches. Never modify models or the supplied query.
     * Preserve caller authorization, tenant constraints and global scopes; exclude the source.
     *
     * @template TModel of Model
     * @param TModel $source
     * @param Builder<TModel> $scope
     * @param positive-int $batchSize
     * @return iterable<TModel>
     */
    public function find(Model $source, Builder $scope, int $batchSize): iterable;
}
