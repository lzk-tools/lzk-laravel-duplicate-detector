<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector;

use Illuminate\Database\Eloquent\Model;
use Lzk\DuplicateDetector\Configuration\DetectorConfigResolver;

final readonly class DuplicateDetectorManager
{
    public function __construct(private DetectorConfigResolver $resolver)
    {
    }

    /**
     * @template TModel of Model
     * @param class-string<TModel> $modelClass
     * @return ConfiguredDetector<TModel>
     */
    public function for(string $modelClass): ConfiguredDetector
    {
        /** @var ConfiguredDetector<TModel> $detector */
        $detector = new ConfiguredDetector($this->resolver->resolve($modelClass));

        return $detector;
    }
}
