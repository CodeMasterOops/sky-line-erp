<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\Auditable;
use App\Traits\HasDiscount;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use Auditable;
    use BranchTenant;
    use HasDiscount;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'fiscal_year_id',
        'party_id',
        'reference_type',
        'reference_id',
        'invoice_no',
        'bijak_no',
        'invoice_date',
        'invoice_date_bs',
        'due_date',
        'remarks',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'voided_at',
        'written_off_at',
        'write_off_amount',
        'write_off_remarks',
        'status',
        'ird_sync_status',
        'ird_internal_id',
        'ird_qr_data',
        'ird_synced_at',
        'ird_error',
        'total_amount',
        'paid_amount',
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'party_id' => 'integer',
        'reference_id' => 'integer',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
        'written_off_at' => 'datetime',
        'write_off_amount' => 'float',
        'ird_synced_at' => 'datetime',
        'status' => StatusEnum::class,
        'total_amount' => 'float',
        'paid_amount' => 'float',
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $query->where(function ($query) use ($param) {
                $key = '%'.trim($param['search']).'%';
                $query->where('invoice_no', 'like', $key);
            });
        }

        if (! empty($param['party_id'])) {
            $query->where('party_id', $param['party_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        return $query;
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function approveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approve_user_id');
    }

    /**
     * Recompute and persist total_amount (from items) and paid_amount (from approved allocations).
     * Call this after invoice items change or after receipt allocations for this invoice change.
     */
    public function refreshTotals(): void
    {
        $orderDiscount = (float) DB::table('discounts')
            ->where('discountable_type', self::class)
            ->where('discountable_id', $this->id)
            ->value('amount');

        $itemTotals = DB::table('invoice_items')
            ->where('invoice_id', $this->id)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(quantity * rate), 0) as subtotal, COALESCE(SUM(discount_amount), 0) as discount_total, COALESCE(SUM(tax_amount), 0) as tax_total')
            ->first();

        $grandTotal = (float) $itemTotals->subtotal
            - (float) $itemTotals->discount_total
            - $orderDiscount
            + (float) $itemTotals->tax_total;

        $receiptPaid = (float) DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipt_allocations.invoice_id', $this->id)
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereNull('receipt_allocations.deleted_at')
            ->whereNull('receipts.deleted_at')
            ->sum('receipt_allocations.amount');

        $advancePaid = (float) DB::table('advance_applications')
            ->where('invoice_id', $this->id)
            ->whereNull('deleted_at')
            ->sum('amount');

        $paidAmount = $receiptPaid + $advancePaid;

        $this->updateQuietly([
            'total_amount' => round($grandTotal, 4),
            'paid_amount' => round($paidAmount, 4),
        ]);
    }
}
