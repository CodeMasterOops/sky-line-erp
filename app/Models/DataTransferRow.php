<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\DataTransfer\DataTransferRowStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransferRow extends Model
{
    protected $fillable = [
        'data_transfer_job_id',
        'row_number',
        'status',
        'raw_payload',
        'normalized_payload',
        'errors',
        'target_type',
        'target_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DataTransferRowStatusEnum::class,
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'errors' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(DataTransferJob::class, 'data_transfer_job_id');
    }
}
