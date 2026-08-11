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
        'modules',
        'is_active',
        'is_default',
        'is_recommended',
        'sort_order',
        'branch_limit',
        'limits',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'features' => 'array',
            'modules' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_recommended' => 'boolean',
            'sort_order' => 'integer',
            'branch_limit' => 'integer',
            'limits' => 'array',
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

    /**
     * Whether this plan lets a company run the given module. A null `modules`
     * column means the plan is uncapped — every plan that predates module
     * entitlements stays that way, so no existing tenant loses anything.
     */
    public function entitlesModule(string $moduleKey): bool
    {
        return $this->modules === null || in_array($moduleKey, $this->modules, true);
    }

    /**
     * The quota this plan sets for the given key, or null for unlimited.
     * Read through QuotaService rather than directly — see config/limits.php.
     */
    public function limitFor(string $key): ?int
    {
        $value = ($this->limits ?? [])[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function priceForCycle(BillingCycleEnum $cycle): string
    {
        return match ($cycle) {
            BillingCycleEnum::Monthly => (string) $this->price_monthly,
            BillingCycleEnum::Yearly => (string) $this->price_yearly,
        };
    }
}
