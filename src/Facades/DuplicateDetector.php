<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Facades;

use Illuminate\Support\Facades\Facade;
use Lzk\DuplicateDetector\DuplicateDetectorManager;

/** @method static \Lzk\DuplicateDetector\ConfiguredDetector<\Illuminate\Database\Eloquent\Model> for(class-string $modelClass) */
final class DuplicateDetector extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DuplicateDetectorManager::class;
    }
}
