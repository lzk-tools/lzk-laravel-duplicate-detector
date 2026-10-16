<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Normalizers;

final class EmailNormalizer extends AbstractStringNormalizer
{
    protected function transform(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
