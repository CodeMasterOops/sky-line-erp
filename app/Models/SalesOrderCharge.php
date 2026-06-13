<?php

namespace App\Models;

use App\Enums\ChargeTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderCharge extends Model
{
    protected $fillable = [
        'sales_order_id',
        'name',
        'charge_type',
        'account_id',
        'amount',
        'tax_id',
        'tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'account_id' => 'integer',
            'tax_id' => 'integer',
            'amount' => 'float',
            'tax_amount' => 'float',
            'charge_type' => ChargeTypeEnum::class,
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
