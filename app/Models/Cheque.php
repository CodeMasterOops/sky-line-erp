<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class Cheque extends Model
{
    use MultiTenant, SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'party_id',
        'bank_account_id',
        'cheque_no',
        'bank_name',
        'bank_branch',
        'cheque_date',
        'deposit_date',
        'cleared_date',
        'amount',
        'type',
        'status',
        'reference_type',
        'reference_id',
        'gl_journal_id',
        'create_user_id',
        'remarks',
    ];

    protected $casts = [
        'cheque_date'  => 'date',
        'deposit_date' => 'date',
        'cleared_date' => 'date',
        'amount'       => 'float',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function glJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'gl_journal_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeDueForPresentation(Builder $query, int $days = 7): Builder
    {
        return $query->where('status', 'pending')
            ->where('cheque_date', '<=', now()->addDays($days)->toDateString());
    }
}
