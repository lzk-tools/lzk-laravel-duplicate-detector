<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Classification;

enum ContradictionRequirement: string
{
    case Any = 'any';
    case None = 'none';
    case Present = 'present';
}
