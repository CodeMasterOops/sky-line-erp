<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\PartyTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Party extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'code',
        'phone',
        'email',
        'pan',
        'address',
        'credit_limit',
        'is_active',
    ];

    protected $casts = [
        'type' => PartyTypeEnum::class,
        'is_active' => 'boolean',
        'credit_limit' => 'float',
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key);
                $q->orWhere('code', 'like', $key);
                $q->orWhere('phone', 'like', $key);
                $q->orWhere('email', 'like', $key);
            });
        }

        if (! empty($param['type'])) {
            $query->where('type', $param['type']);
        }

        return $query;
    }

    public function setCreditLimitAttribute($value): void
    {
        $this->attributes['credit_limit'] = floatval($value);
    }
}
