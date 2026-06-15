<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceApplication extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_advance_id',
        'invoice_id',
        'amount',
        'applied_at',
        'apply_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'applied_at' => 'date',
        ];
    }

    public function customerAdvance(): BelongsTo
    {
        return $this->belongsTo(CustomerAdvance::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function applyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'apply_user_id');
    }
}
