<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Normalizers;

final class TextNormalizer extends AbstractStringNormalizer
{
    protected function transform(string $value): string
    {
        return mb_strtolower((string) preg_replace('/[\s\p{Z}]+/u', ' ', $value), 'UTF-8');
    }
}
