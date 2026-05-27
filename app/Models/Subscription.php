<?php

namespace App\Models;

use App\Enums\BillingCycleEnum;
use App\Enums\SubscriptionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'billing_cycle',
        'price',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'assigned_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatusEnum::class,
            'billing_cycle' => BillingCycleEnum::class,
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', SubscriptionStatusEnum::activeStatuses());
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->whereHas('company', function ($q) use ($key) {
                $q->where('company_name', 'like', $key)
                    ->orWhere('email', 'like', $key);
            });
        }

        if (! empty($param['company_id'])) {
            $query->where('company_id', $param['company_id']);
        }

        if (! empty($param['plan_id'])) {
            $query->where('plan_id', $param['plan_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        return $query;
    }

    public function monthlyRecurringRevenue(): float
    {
        $price = (float) $this->price;

        return match ($this->billing_cycle) {
            BillingCycleEnum::Monthly => $price,
            BillingCycleEnum::Yearly => round($price / 12, 2),
        };
    }
}
