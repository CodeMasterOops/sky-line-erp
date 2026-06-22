<?php

namespace App\Models;

use App\Traits\HasTags;
use App\Traits\HasNotes;
use App\Traits\HasDiscount;
use App\Traits\MultiTenant;
use App\Enums\PartyTypeEnum;
use App\Traits\HasActivities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Party extends Model
{
    use HasActivities;
    use HasDiscount;
    use HasNotes;
    use HasTags;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'import_batch_id',
        'type',
        'name',
        'code',
        'phone',
        'email',
        'pan',
        'address',
        'credit_limit',
        'is_active',
    ];

    protected $casts = [
        'type' => PartyTypeEnum::class,
        'is_active' => 'boolean',
        'credit_limit' => 'float',
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key);
                $q->orWhere('code', 'like', $key);
                $q->orWhere('phone', 'like', $key);
                $q->orWhere('email', 'like', $key);
            });
        }

        if (! empty($param['type'])) {
            $query->where('type', $param['type']);
        }

        if (! empty($param['status'])) {
            $query->whereHas('leadProfile', function ($q) use ($param) {
                $q->where('status', $param['status']);
            });
        }

        return $query;
    }

    public function setCreditLimitAttribute($value): void
    {
        $this->attributes['credit_limit'] = floatval($value);
    }

    public function leadProfile(): HasOne
    {
        return $this->hasOne(CrmLeadProfile::class);
    }

    public function contactPersons(): HasMany
    {
        return $this->hasMany(ContactPerson::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}
