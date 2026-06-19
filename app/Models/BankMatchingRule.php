<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankMatchingRule extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'priority',
        'match_field',
        'operator',
        'pattern',
        'target_account_id',
        'set_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'target_account_id');
    }

    /**
     * Whether this rule's pattern matches a statement line's description/reference.
     */
    public function matches(BankStatementLine $line): bool
    {
        $haystack = (string) ($this->match_field === 'reference' ? $line->reference : $line->description);

        return match ($this->operator) {
            'equals' => mb_strtolower(trim($haystack)) === mb_strtolower(trim($this->pattern)),
            'regex' => @preg_match('/'.$this->pattern.'/i', $haystack) === 1,
            default => $this->pattern !== '' && mb_stripos($haystack, $this->pattern) !== false,
        };
    }
}
