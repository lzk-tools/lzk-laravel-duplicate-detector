<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Contracts;

interface NormalizerInterface
{
    /**
     * Normalize one value without changing the source. Null denotes missing data.
     * Implementations must document accepted options and reject unsupported inputs.
     *
     * @param array<string, mixed> $options
     */
    public function normalize(string|int|float|bool|null $value, array $options = []): string|int|float|bool|null;
}
