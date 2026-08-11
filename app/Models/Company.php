<?php

namespace App\Models;

use App\Enums\UserTypeEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fiscal_year_id',
        'company_category_id',
        'company_name',
        'code',
        'legal_name',
        'pan',
        'logo',
        'invoice_note',
        'phone',
        'landline',
        'email',
        'website',
        'address',
        'ward_id',
        'postal_code',
        'is_active',
        'inventory_costing_method',
        'ird_username',
        'ird_password',
        'ird_branch_office',
        'ird_unit_name',
        'ird_fiscal_device',
        'ird_ebs_enabled',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ird_ebs_enabled' => 'boolean',
            'inventory_costing_method' => InventoryCostingMethodEnum::class,
            'onboarding_completed_at' => 'datetime',
            'ird_password' => 'encrypted',
        ];
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? Storage::disk('public')->url($this->logo)
            : '';
    }

    public function logoAbsolutePath(): ?string
    {
        if (! $this->logo || ! Storage::disk('public')->exists($this->logo)) {
            return null;
        }

        return Storage::disk('public')->path($this->logo);
    }

    public function setLogoAttribute($value): void
    {
        if (! empty($value) && $value instanceof UploadedFile) {
            if ($this->logo && Storage::disk('public')->exists($this->logo)) {
                Storage::disk('public')->delete($this->logo);
            }

            $this->attributes['logo'] = $value->store('company/logo', 'public');
        }
    }

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('company_name', 'like', $key)
                    ->orWhere('email', 'like', $key)
                    ->orWhere('phone', 'like', $key);
            });
        }

        return $query;
    }

    public function admin(): HasOne
    {
        return $this->hasOne(User::class)->where('user_type', UserTypeEnum::ADMIN->value);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class, 'company_category_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CompanyModule::class);
    }

    public function moduleEvents(): HasMany
    {
        return $this->hasMany(CompanyModuleEvent::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->active()
            ->latestOfMany();
    }

    /**
     * The plan that caps this company's modules.
     *
     * Two deliberate choices:
     *
     * 1. **Newest first.** Overlapping active subscriptions are possible, and
     *    an unordered `first()` made the module cap depend on row order.
     * 2. **A lapse does not uncap.** When no subscription is active the last
     *    plan still applies (see `effectivePlan()`), because losing the cap on
     *    non-payment would silently *widen* what a company can run.
     */
    public function plan(): HasOneThrough
    {
        return $this->hasOneThrough(
            Plan::class,
            Subscription::class,
            'company_id',
            'id',
            'id',
            'plan_id'
        )
            ->whereIn('subscriptions.status', \App\Enums\SubscriptionStatusEnum::activeStatuses())
            ->orderByDesc('subscriptions.starts_at')
            ->orderByDesc('subscriptions.id');
    }

    /**
     * The plan to resolve modules against: the active one, or — when every
     * subscription has lapsed — the most recent plan the company was on.
     */
    public function effectivePlan(): ?Plan
    {
        $active = $this->plan()->first();

        if ($active) {
            return $active;
        }

        return Subscription::query()
            ->where('company_id', $this->id)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first()?->plan;
    }
}
