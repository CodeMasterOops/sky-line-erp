<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\CheckInMethodEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * One visit. Deliberately not the same thing as HR `Attendance`, which is a
 * staff record with leave and payroll consequences.
 */
class MemberCheckIn extends Model
{
    /** @use HasFactory<\Database\Factories\MemberCheckInFactory> */
    use BranchTenant;
    use HasFactory;
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'member_id',
        'membership_id',
        'checked_in_at',
        'checked_out_at',
        'method',
        'device_ref',
        'notes',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'method' => CheckInMethodEnum::class,
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('checked_out_at');
    }

    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('checked_in_at', $date);
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->whereHas('member', function ($memberQuery) use ($key) {
                $memberQuery->where('member_code', 'like', $key)
                    ->orWhereHas('party', fn ($p) => $p->where('name', 'like', $key));
            });
        }

        if (! empty($param['member_id'])) {
            $query->where('member_id', $param['member_id']);
        }

        if (! empty($param['date'])) {
            $query->onDate($param['date']);
        }

        if (! empty($param['from'])) {
            $query->whereDate('checked_in_at', '>=', $param['from']);
        }

        if (! empty($param['to'])) {
            $query->whereDate('checked_in_at', '<=', $param['to']);
        }

        if (! empty($param['open'])) {
            $query->open();
        }

        return $query;
    }
}
