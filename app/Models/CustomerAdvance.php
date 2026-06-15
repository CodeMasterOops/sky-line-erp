<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAdvance extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'party_id',
        'advance_no',
        'advance_date',
        'amount',
        'applied_amount',
        'payment_method',
        'account_id',
        'reference_no',
        'remarks',
        'status',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'applied_amount' => 'float',
            'advance_date' => 'date',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
            'status' => StatusEnum::class,
        ];
    }

    public function remainingAmount(): float
    {
        return round(max((float) $this->amount - (float) $this->applied_amount, 0), 4);
    }

    public function isFullyApplied(): bool
    {
        return $this->remainingAmount() <= 0;
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function approveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approve_user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdvanceApplication::class);
    }

    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}
