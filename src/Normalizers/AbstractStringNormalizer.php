<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Normalizers;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\NormalizerInterface;

abstract class AbstractStringNormalizer implements NormalizerInterface
{
    /** @param array<string, mixed> $options */
    final public function normalize(string|int|float|bool|null $value, array $options = []): ?string
    {
        if ($options !== []) {
            throw new InvalidArgumentException('This normalizer does not accept options.');
        }

        if ($value === null) {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException('Normalization requires a UTF-8 string or null.');
        }

        $value = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value);
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = $this->transform($value);

        return $normalized === '' ? null : $normalized;
    }

    abstract protected function transform(string $value): string;
}
