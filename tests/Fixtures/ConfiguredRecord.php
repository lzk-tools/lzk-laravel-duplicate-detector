<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ConfiguredRecord extends Model
{
    protected $table = 'configured_records';

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('active', static function (Builder $builder): void {
            $builder->where('active', true);
        });
    }

    public function getSecretAttribute(): never
    {
        throw new \RuntimeException('The public detector must not invoke accessors.');
    }
}
