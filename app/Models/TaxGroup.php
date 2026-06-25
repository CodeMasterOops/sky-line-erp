<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxGroup extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'is_active',
        'is_system',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function taxGroupMembers(): HasMany
    {
        return $this->hasMany(TaxGroupMember::class)->orderBy('sequence');
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'tax_group_members')
            ->withPivot('sequence')
            ->orderByPivot('sequence');
    }
}
