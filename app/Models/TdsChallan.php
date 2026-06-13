<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\TdsChallanStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsChallan extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'challan_no',
        'challan_date',
        'party_id',
        'period_month',
        'total_tds_amount',
        'payment_date',
        'bank_name',
        'remarks',
        'status',
        'create_user_id',
    ];

    protected function casts(): array
    {
        return [
            'challan_date' => 'date',
            'payment_date' => 'date',
            'total_tds_amount' => 'float',
            'period_month' => 'integer',
            'status' => TdsChallanStatusEnum::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TdsChallanItem::class);
    }
}
