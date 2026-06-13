<?php

namespace App\Models;

use App\Enums\ChargeTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceCharge extends Model
{
    protected $fillable = [
        'invoice_id',
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
            'invoice_id' => 'integer',
            'account_id' => 'integer',
            'tax_id' => 'integer',
            'amount' => 'float',
            'tax_amount' => 'float',
            'charge_type' => ChargeTypeEnum::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
