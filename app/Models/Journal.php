<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\Auditable;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\JournalTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    use Auditable;
    use BranchTenant;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'fiscal_year_id',
        'type',
        'reference_type',
        'reference_id',
        'voucher_no',
        'reference_no',
        'date',
        'remarks',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'fiscal_year_id' => 'integer',
        'approved_at' => 'datetime',
        'type' => JournalTypeEnum::class,
        'status' => StatusEnum::class,
    ];

    /**
     * Closed-period immutability: a posted journal that already lives inside a
     * closed/locked accounting period may not be edited in place or force-deleted.
     * Soft-deletes (the audit-preserving void path) bypass the model `updating`
     * event, so reversals via a contra entry remain available. New postings into
     * a closed period are blocked separately by PeriodLockGuard.
     */
    protected static function booted(): void
    {
        static::updating(function (Journal $journal) {
            app(\App\Services\Accounting\PeriodLockGuard::class)->assertPostable(
                $journal->company_id,
                $journal->fiscal_year_id,
                $journal->getOriginal('date'),
            );
        });

        static::deleting(function (Journal $journal) {
            if (method_exists($journal, 'isForceDeleting') && $journal->isForceDeleting()) {
                app(\App\Services\Accounting\PeriodLockGuard::class)->assertPostable(
                    $journal->company_id,
                    $journal->fiscal_year_id,
                    $journal->getOriginal('date'),
                );
            }
        });
    }

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('voucher_no', 'like', $key);
                $q->orWhere('reference_no', 'like', $key);
            });
        }

        return $query;
    }

    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function approveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approve_user_id');
    }
}
