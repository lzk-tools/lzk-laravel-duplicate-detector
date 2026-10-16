<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactRecord extends Model
{
    use SoftDeletes;

    protected $table = 'detector_records';

    public $timestamps = false;

    /** @return HasMany<self, $this> */
    public function peers(): HasMany
    {
        throw new \LogicException('Detection must not eager-load relations.');
    }

    public function getEmailAttribute(): never
    {
        throw new \LogicException('Detection must not invoke accessors.');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $builder): void {
            $builder->where('active', '=', 1);
        });
    }
}
