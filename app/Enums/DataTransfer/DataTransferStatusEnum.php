<?php

namespace App\Enums\DataTransfer;

enum DataTransferStatusEnum: string
{
    case Uploaded = 'uploaded';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case Mapped = 'mapped';
    case Validating = 'validating';
    case Validated = 'validated';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RolledBack = 'rolled_back';
}
