<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\DTO;

enum FieldOutcome: string
{
    case Match = 'match';
    case Difference = 'difference';
    case Missing = 'missing';
    case Contradiction = 'contradiction';
}
