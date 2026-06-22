<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\FollowUpStatusEnum;
use App\Enums\FollowUpChannelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FollowUp extends Model
{
    use BranchTenant;
    /** @use HasFactory<\Database\Factories\FollowUpFactory> */
    use HasFactory;
    use MultiTenant;
    use SoftDeletes;

    protected $table = 'crm_follow_ups';

    protected $fillable = [
        'company_id',
        'branch_id',
        'party_id',
        'user_id',
        'channel',
        'scheduled_at',
        'status',
        'outcome',
        'note',
        'completed_at',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => FollowUpChannelEnum::class,
            'status' => FollowUpStatusEnum::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $param
     */
    public function scopeFilter(Builder $query, array $param = []): Builder
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function (Builder $q) use ($key) {
                $q->where('note', 'like', $key)
                    ->orWhere('outcome', 'like', $key);
            });
        }

        if (! empty($param['party_id'])) {
            $query->where('party_id', $param['party_id']);
        }

        if (! empty($param['user_id'])) {
            $query->where('user_id', $param['user_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        if (! empty($param['channel'])) {
            $query->where('channel', $param['channel']);
        }

        return $query;
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
