<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    use MultiTenant, SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'bom_id',
        'warehouse_id',
        'order_no',
        'planned_qty',
        'produced_qty',
        'status',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'gl_journal_id',
        'remarks',
    ];

    protected $casts = [
        'planned_qty' => 'float',
        'produced_qty' => 'float',
        'planned_start' => 'date',
        'planned_end' => 'date',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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

    public function glJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'gl_journal_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionOrderConsumption::class);
    }
}
