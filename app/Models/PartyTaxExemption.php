<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyTaxExemption extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'party_id',
        'tax_id',
        'exemption_reason',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'party_id' => 'integer',
        'tax_id' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function isActiveOn(\DateTimeInterface|string $date): bool
    {
        $checkDate = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : $date;

        if ($this->valid_from && $checkDate < $this->valid_from->format('Y-m-d')) {
            return false;
        }

        if ($this->valid_to && $checkDate > $this->valid_to->format('Y-m-d')) {
            return false;
        }

        return true;
    }
}
