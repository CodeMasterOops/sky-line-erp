<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_account_id',
        'import_id',
        'transaction_date',
        'description',
        'reference',
        'external_ref',
        'debit',
        'credit',
        'balance',
        'status',
        'match_type',
        'hash',
        'journal_item_id',
        'reconciliation_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
        'balance' => 'float',
    ];

    /**
     * Stable fingerprint used to detect duplicate statement lines on re-import.
     * Combined with bank_account_id it forms a unique key.
     */
    public static function makeHash(
        int $bankAccountId,
        mixed $transactionDate,
        float|string|null $debit,
        float|string|null $credit,
        ?string $reference,
        float|string|null $balance,
    ): string {
        $date = $transactionDate instanceof \DateTimeInterface
            ? $transactionDate->format('Y-m-d')
            : Carbon::parse((string) $transactionDate)->toDateString();

        return sha1(implode('|', [
            $bankAccountId,
            $date,
            number_format((float) $debit, 2, '.', ''),
            number_format((float) $credit, 2, '.', ''),
            trim((string) $reference),
            $balance === null ? '' : number_format((float) $balance, 2, '.', ''),
        ]));
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalItem(): BelongsTo
    {
        return $this->belongsTo(JournalItem::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'import_id');
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    /**
     * Signed statement amount: positive for money in (credit), negative for money out (debit).
     */
    public function signedAmount(): float
    {
        return round((float) $this->credit - (float) $this->debit, 2);
    }
}
