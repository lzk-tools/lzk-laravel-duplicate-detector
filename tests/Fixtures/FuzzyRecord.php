<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FuzzyRecord extends Model
{
    use SoftDeletes;

    protected $table = 'fuzzy_records';

    public $timestamps = false;

    public function getNameAttribute(): never
    {
        throw new \LogicException('Fuzzy detection must not invoke accessors.');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $builder): void {
            $builder->where('active', '=', 1);
        });
    }
}
