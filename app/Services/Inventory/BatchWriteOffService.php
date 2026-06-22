<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Models\Batch;
use App\Models\Company;
use App\Enums\StatusEnum;
use App\Models\DamageReport;
use App\Enums\ChangeTypeEnum;
use App\Enums\BatchStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchWriteOffService
{
    public function __construct(private InventoryLayerIssueService $issue) {}

    /**
     * Write off the entire remaining quantity of a held lot (recalled, quarantine or
     * expired) by raising an approved Damage Report. This removes the stock from on-hand
     * and the cost ledger and posts the loss to the GL (Damage Expense Dr / Inventory Cr)
     * via the stock-movement observer.
     */
    public function writeOffRemaining(Batch $batch, User $user, ?string $reason = null): DamageReport
    {
        return DB::transaction(function () use ($batch, $user, $reason) {
            Batch::reconcileRemaining($batch->id);
            $batch->refresh();

            $remaining = (float) $batch->remaining_qty;

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'batch_id' => __('Batch :no has no remaining quantity to write off.', ['no' => $batch->batch_no]),
                ]);
            }

            $company = Company::findOrFail($batch->company_id);
            $statusLabel = $batch->status instanceof BatchStatusEnum
                ? $batch->status->label()
                : (string) $batch->status;

            $report = DamageReport::create([
                'company_id' => $batch->company_id,
                'date' => now()->toDateString(),
                'warehouse_id' => $batch->warehouse_id,
                'reason' => $reason ?? __(':status batch write-off', ['status' => $statusLabel]),
                'remarks' => __('Write-off of batch :no', ['no' => $batch->batch_no]),
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);

            $report->damageReportItems()->create([
                'product_variant_id' => $batch->product_variant_id,
                'quantity' => $remaining,
                'unit_cost' => $batch->unit_cost,
                'batch_id' => $batch->id,
                'remarks' => $reason,
            ]);

            $this->issue->issue(
                $company,
                $report,
                (int) $batch->product_variant_id,
                (int) $batch->warehouse_id,
                $remaining,
                ChangeTypeEnum::DAMAGE,
                $user->id,
                $report->remarks,
                $batch->id,
                allowHeldBatch: true,
            );

            return $report;
        });
    }
}
