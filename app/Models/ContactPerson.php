<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactPerson extends Model
{
    /** @use HasFactory<\Database\Factories\ContactPersonFactory> */
    use HasFactory;
    use MultiTenant;
    use SoftDeletes;

    protected $table = 'crm_contact_persons';

    protected $fillable = [
        'company_id',
        'party_id',
        'name',
        'designation',
        'phone',
        'email',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['party_id'])) {
            $query->where('party_id', $param['party_id']);
        }

        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key)
                    ->orWhere('phone', 'like', $key)
                    ->orWhere('email', 'like', $key);
            });
        }

        return $query;
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
