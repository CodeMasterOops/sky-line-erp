<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCashMovement extends Model
{
    use BranchTenant;
    use MultiTenant;

    protected $fillable = [
        'pos_session_id',
        'company_id',
        'branch_id',
        'user_id',
        'type',
        'amount',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
