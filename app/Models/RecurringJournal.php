<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringJournal extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'frequency',
        'next_run_date',
        'end_date',
        'remarks',
        'is_active',
        'created_by',
        'last_run_at',
    ];

    protected $casts = [
        'next_run_date' => 'date',
        'end_date' => 'date',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RecurringJournalItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
