<?php

namespace App\Services\Crm;

use App\Models\Task;
use App\Models\Party;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\FollowUp;
use App\Models\SalesOrder;
use App\Enums\TaskStatusEnum;
use App\Services\TenantService;
use App\Enums\FollowUpStatusEnum;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the Customer 360 summary by reusing the existing Sales / Accounting
 * Eloquent models — every sub-query is branch-scoped automatically by the
 * BranchTenant trait, so a user only ever sees figures for branches they can
 * access. The cache key therefore embeds the current scope (company + branch or
 * user) so cached aggregates can never leak across tenants or branches.
 */
class CustomerProfileAggregator
{
    private const CACHE_TTL = 90;

    /**
     * @return array<string, mixed>
     */
    public function summary(Party $party): array
    {
        return Cache::remember(
            $this->cacheKey($party->id),
            self::CACHE_TTL,
            fn (): array => $this->build($party),
        );
    }

    public function forget(int $partyId): void
    {
        Cache::forget($this->cacheKey($partyId));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Party $party): array
    {
        $invoices = Invoice::query()
            ->where('party_id', $party->id)
            ->whereNull('voided_at');

        $outstanding = (float) (clone $invoices)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as aggregate')
            ->value('aggregate');

        return [
            'outstanding_balance' => round($outstanding, 2),
            'lifetime_value' => round((float) (clone $invoices)->sum('total_amount'), 2),
            'invoice_count' => (clone $invoices)->count(),
            'recent_invoices' => (clone $invoices)
                ->latest('invoice_date')
                ->limit(5)
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'invoice_date' => $invoice->invoice_date,
                    'total_amount' => (float) $invoice->total_amount,
                    'paid_amount' => (float) $invoice->paid_amount,
                    'status' => $invoice->status?->value,
                ])->all(),
            'recent_receipts' => Receipt::query()
                ->where('party_id', $party->id)
                ->withSum('allocations as amount', 'amount')
                ->latest('receipt_date')
                ->limit(5)
                ->get()
                ->map(fn (Receipt $receipt): array => [
                    'id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_date' => $receipt->receipt_date,
                    'amount' => round((float) $receipt->amount, 2),
                ])->all(),
            'recent_sales' => SalesOrder::query()
                ->where('party_id', $party->id)
                ->latest('order_date')
                ->limit(5)
                ->get()
                ->map(fn (SalesOrder $order): array => [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'order_date' => $order->order_date,
                    'status' => $order->status?->value,
                ])->all(),
            'open_follow_ups' => FollowUp::query()
                ->where('party_id', $party->id)
                ->where('status', FollowUpStatusEnum::Pending->value)
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get()
                ->map(fn (FollowUp $followUp): array => [
                    'id' => $followUp->id,
                    'channel' => $followUp->channel?->value,
                    'channel_label' => $followUp->channel?->label(),
                    'scheduled_at' => $followUp->scheduled_at?->toIso8601String(),
                ])->all(),
            'open_tasks' => Task::query()
                ->where('taskable_type', Party::class)
                ->where('taskable_id', $party->id)
                ->whereNotIn('status', [TaskStatusEnum::Done->value, TaskStatusEnum::Cancelled->value])
                ->orderByRaw('due_date is null, due_date asc')
                ->limit(5)
                ->get()
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'priority' => $task->priority?->value,
                    'status' => $task->status?->value,
                    'due_date' => $task->due_date?->toDateString(),
                ])->all(),
        ];
    }

    private function cacheKey(int $partyId): string
    {
        $companyId = TenantService::companyId() ?? 0;

        if ($branchId = TenantService::branchId()) {
            $scope = "b{$branchId}";
        } elseif (($user = auth('admin')->user()) && ! $user->isAdmin()) {
            $scope = "u{$user->id}";
        } else {
            $scope = 'all';
        }

        return "crm:customer:{$companyId}:{$scope}:{$partyId}:summary";
    }
}
