<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Normalizers;

use InvalidArgumentException;

final class PhoneNormalizer extends AbstractStringNormalizer
{
    protected function transform(string $value): string
    {
        $value = (string) preg_replace('/[\s\p{Z}\p{Pd}().\/]+/u', '', $value);

        if ($value !== '' && preg_match('/^\+?[0-9]+$/D', $value) !== 1) {
            throw new InvalidArgumentException('A phone must contain digits, an optional leading plus and separators.');
        }

        return $value;
    }
}
