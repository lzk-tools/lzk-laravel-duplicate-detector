<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Fixtures;

final class UuidRecord extends ContactRecord
{
    protected $table = 'uuid_records';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;
}
