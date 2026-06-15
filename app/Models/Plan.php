<?php

namespace App\Models;

use App\Enums\BillingCycleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'features',
        'is_active',
        'is_default',
        'is_recommended',
        'sort_order',
        'branch_limit',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_recommended' => 'boolean',
            'sort_order' => 'integer',
            'branch_limit' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key)
                    ->orWhere('slug', 'like', $key);
            });
        }

        if (isset($param['is_active'])) {
            $query->where('is_active', filter_var($param['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    public function priceForCycle(BillingCycleEnum $cycle): string
    {
        return match ($cycle) {
            BillingCycleEnum::Monthly => (string) $this->price_monthly,
            BillingCycleEnum::Yearly => (string) $this->price_yearly,
        };
    }
}
