<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsCertificateSequence extends Model
{
    protected $fillable = [
        'company_id',
        'fiscal_year_code',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Atomically increments the sequence counter and returns the new sequential number.
     * Uses a SELECT ... FOR UPDATE lock to prevent concurrent duplicates.
     */
    public static function nextFor(int $companyId, string $fiscalYearCode): int
    {
        $row = static::where('company_id', $companyId)
            ->where('fiscal_year_code', $fiscalYearCode)
            ->lockForUpdate()
            ->first();

        if ($row) {
            $next = $row->last_sequence + 1;
            $row->update(['last_sequence' => $next]);
        } else {
            $next = 1;
            static::create([
                'company_id' => $companyId,
                'fiscal_year_code' => $fiscalYearCode,
                'last_sequence' => $next,
            ]);
        }

        return $next;
    }
}
