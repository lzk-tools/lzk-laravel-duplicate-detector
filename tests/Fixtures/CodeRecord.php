<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Fixtures;

final class CodeRecord extends ContactRecord
{
    protected $table = 'code_records';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;
}
