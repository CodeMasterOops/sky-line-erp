<?php

namespace App\Models;

use App\Enums\ChargeTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteCharge extends Model
{
    protected $fillable = [
        'credit_note_id',
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
            'credit_note_id' => 'integer',
            'account_id' => 'integer',
            'tax_id' => 'integer',
            'amount' => 'float',
            'tax_amount' => 'float',
            'charge_type' => ChargeTypeEnum::class,
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
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
