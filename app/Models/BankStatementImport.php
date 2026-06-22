<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementImport extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'file_name',
        'file_hash',
        'source',
        'column_map',
        'row_count',
        'imported_count',
        'skipped_count',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'row_count' => 'integer',
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'import_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
