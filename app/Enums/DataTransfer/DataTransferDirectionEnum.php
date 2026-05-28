<?php

namespace App\Enums\DataTransfer;

enum DataTransferDirectionEnum: string
{
    case Import = 'import';
    case Export = 'export';
}
