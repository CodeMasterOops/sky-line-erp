<?php

namespace App\Models;

use App\Enums\LeadStatusEnum;
use App\Enums\BusinessTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lead extends Model
{
    /** @use HasFactory<\Database\Factories\LeadFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_name',
        'pan',
        'registration_number',
        'business_type',
        'full_name',
        'email',
        'phone',
        'plan_interest',
        'branch_count',
        'note',
        'status',
        'follow_up_note',
        'followed_up_at',
        'ip_address',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessTypeEnum::class,
            'status' => LeadStatusEnum::class,
            'branch_count' => 'integer',
            'followed_up_at' => 'datetime',
        ];
    }

    public function scopeFilter($query, array $params = [])
    {
        if (! empty($params['search'])) {
            $key = '%'.trim($params['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('company_name', 'like', $key)
                    ->orWhere('full_name', 'like', $key)
                    ->orWhere('email', 'like', $key)
                    ->orWhere('phone', 'like', $key);
            });
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['business_type'])) {
            $query->where('business_type', $params['business_type']);
        }

        return $query;
    }
}
