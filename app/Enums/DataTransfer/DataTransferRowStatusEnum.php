<?php

namespace App\Enums\DataTransfer;

enum DataTransferRowStatusEnum: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Imported = 'imported';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
