<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\DataTransfer\DataTransferDirectionEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;

class DataTransferJob extends Model
{
    use MultiTenant;

    protected $fillable = [
        'uuid',
        'company_id',
        'branch_id',
        'user_id',
        'direction',
        'entity_type',
        'status',
        'file_disk',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'file_hash',
        'options',
        'mapping',
        'stats',
        'error_summary',
        'result_disk',
        'result_path',
        'batch_id',
        'started_at',
        'finished_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => DataTransferDirectionEnum::class,
            'entity_type' => DataTransferEntityTypeEnum::class,
            'status' => DataTransferStatusEnum::class,
            'options' => 'array',
            'mapping' => 'array',
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DataTransferJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
            if (empty($job->stats)) {
                $job->stats = [
                    'total_rows' => 0,
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'valid' => 0,
                    'invalid' => 0,
                ];
            }
            if ($job->direction === DataTransferDirectionEnum::Import && empty($job->batch_id)) {
                $job->batch_id = (string) Str::uuid();
            }
            if (empty($job->expires_at)) {
                $job->expires_at = now()->addDays((int) config('data_transfer.retention_days', 14));
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DataTransferRow::class);
    }

    public function mergeStats(array $patch): void
    {
        $this->stats = array_merge($this->stats ?? [], $patch);
        $this->save();
    }

    public function incrementStat(string $key, int $by = 1): void
    {
        $stats = $this->stats ?? [];
        $stats[$key] = ($stats[$key] ?? 0) + $by;
        $this->stats = $stats;
        $this->save();
    }
}
