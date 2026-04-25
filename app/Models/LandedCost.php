<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCost extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'goods_received_note_id',
        'cost_type',
        'description',
        'amount',
        'account_id',
        'journal_id',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
