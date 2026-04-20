<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\MultiTenant;
use App\Enums\JournalTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
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
        'fiscal_year_id' => 'integer',
        'approved_at' => 'datetime',
        'type' => JournalTypeEnum::class,
        'status' => StatusEnum::class,
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('voucher_no', 'like', $key);
                $q->orWhere('reference_no', 'like', $key);
            });
        }

        if (! empty($param['product_category_id'])) {
            $query->where('product_category_id', $param['product_category_id']);
        }

        if (! empty($param['brand_id'])) {
            $query->where('brand_id', $param['brand_id']);
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
