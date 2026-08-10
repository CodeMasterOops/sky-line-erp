<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\MembershipStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * One membership term for one member.
 *
 * Terms are immutable history: renewing inserts a new row chained through
 * `renewed_from_id` rather than extending this one, so the invoice behind each
 * term stays attached to the period it actually paid for.
 *
 * NOT to be confused with App\Models\Subscription, which is the SaaS
 * subscription a *company* holds.
 */
class Membership extends Model
{
    /** @use HasFactory<\Database\Factories\MembershipFactory> */
    use Auditable;
    use BranchTenant;
    use HasFactory;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'member_id',
        'membership_plan_id',
        'membership_no',
        'start_date',
        'end_date',
        'status',
        'price',
        'discount_amount',
        'joining_fee',
        'payable_amount',
        'invoice_id',
        'renewed_from_id',
        'freeze_days_used',
        'reminders_sent',
        'expired_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            // 'date:Y-m-d', never plain 'date' — SQLite stores the latter with a
            // time component and the expiry sweep compares on equality.
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'status' => MembershipStatusEnum::class,
            'price' => 'float',
            'discount_amount' => 'float',
            'joining_fee' => 'float',
            'payable_amount' => 'float',
            'freeze_days_used' => 'integer',
            'reminders_sent' => 'array',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(MembershipFreeze::class)->orderByDesc('from_date');
    }

    /**
     * The freeze currently pausing this term, if it is paused.
     */
    public function runningFreeze(): ?MembershipFreeze
    {
        return $this->freezes()->running()->first();
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(MemberCheckIn::class);
    }

    /**
     * The last day this term is honoured, including the plan's grace period.
     */
    public function graceEndDate(): \Carbon\CarbonInterface
    {
        $graceDays = (int) ($this->membershipPlan?->grace_days ?? 0);

        return $this->end_date->copy()->addDays($graceDays);
    }

    public function daysRemaining(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    public function isCurrent(): bool
    {
        return in_array($this->status, MembershipStatusEnum::occupyingStatuses(), true);
    }

    public function scopeCurrent($query)
    {
        return $query->whereIn('status', MembershipStatusEnum::occupyingValues());
    }

    public function scopeActive($query)
    {
        return $query->where('status', MembershipStatusEnum::Active->value);
    }

    /**
     * Active terms whose last day falls on or before the given date.
     */
    public function scopeExpiringBy($query, string $date)
    {
        return $query->active()->whereDate('end_date', '<=', $date);
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function (Builder $q) use ($key) {
                $q->where('membership_no', 'like', $key)
                    ->orWhereHas('member', function (Builder $memberQuery) use ($key) {
                        $memberQuery->where('member_code', 'like', $key)
                            ->orWhereHas('party', fn (Builder $p) => $p->where('name', 'like', $key));
                    });
            });
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        if (! empty($param['member_id'])) {
            $query->where('member_id', $param['member_id']);
        }

        if (! empty($param['membership_plan_id'])) {
            $query->where('membership_plan_id', $param['membership_plan_id']);
        }

        if (! empty($param['expiring_in'])) {
            $query->expiringBy(now()->addDays((int) $param['expiring_in'])->toDateString());
        }

        return $query;
    }
}
