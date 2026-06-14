<?php

namespace App\Models;

use App\Enums\ChequeStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'payment_mode_id',
        'payment_method',
        'account_id',
        'amount',
        'reference_no',
        'cheque_date',
        'cheque_status',
    ];

    protected function casts(): array
    {
        return [
            'payment_mode_id' => 'integer',
            'account_id' => 'integer',
            'amount' => 'decimal:2',
            'cheque_status' => ChequeStatusEnum::class,
            'cheque_date' => 'date',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }
}
