<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use App\Models\Party;
use App\Models\Invoice;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Annotation\Permissions;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PartyResource;
use App\Services\Crm\CustomerProfileAggregator;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerProfileController extends Controller
{
    public function __construct(
        private CustomerProfileAggregator $aggregator,
    ) {}

    /**
     * Customer 360 summary: party details + live Sales / Accounting aggregates.
     */
    #[Permissions('view_crm_customer_360', group: 'crm_customer', desc: 'View Customer 360')]
    public function summary(Party $party)
    {
        $party->load(['leadProfile.assignedUser', 'contactPersons', 'tags']);

        return response()->json([
            'data' => array_merge(
                ['party' => PartyResource::make($party)],
                $this->aggregator->summary($party),
            ),
        ]);
    }

    /**
     * Unified, read-only activity feed: persisted CRM-native activities merged at
     * read-time with financial events (invoices, receipts) which remain the
     * single source of truth in Sales / Accounting. Ordered newest-first.
     */
    #[Permissions('view_crm_timeline', group: 'crm_customer', desc: 'View Customer Timeline')]
    public function timeline(Request $request, Party $party)
    {
        $items = $this->crmActivities($party)
            ->concat($this->financialActivities($party))
            ->sortByDesc(fn (array $item): string => $item['sort_key'])
            ->values();

        $perPage = (int) ($request->limit ?? 25);
        $page = (int) ($request->page ?? 1);

        $paginated = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->map(fn (array $item): array => collect($item)->except('sort_key')->all())->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function crmActivities(Party $party): Collection
    {
        return $party->activities()
            ->with('causer')
            ->get()
            ->map(fn ($activity): array => [
                'id' => 'crm-'.$activity->id,
                'source' => 'crm',
                'type' => $activity->type,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'causer_name' => $activity->causer?->name,
                'occurred_at' => $activity->occurred_at?->toIso8601String(),
                'sort_key' => $activity->occurred_at?->toIso8601String() ?? '',
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function financialActivities(Party $party): Collection
    {
        $invoices = Invoice::query()
            ->where('party_id', $party->id)
            ->whereNull('voided_at')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => 'invoice-'.$invoice->id,
                'source' => 'finance',
                'type' => 'invoice_created',
                'description' => 'Invoice '.$invoice->invoice_no.' created',
                'properties' => ['invoice_no' => $invoice->invoice_no, 'amount' => (float) $invoice->total_amount],
                'causer_name' => null,
                'occurred_at' => $this->toIso($invoice->invoice_date),
                'sort_key' => $this->toIso($invoice->invoice_date) ?? '',
            ]);

        $receipts = Receipt::query()
            ->where('party_id', $party->id)
            ->withSum('allocations as amount', 'amount')
            ->get()
            ->map(fn (Receipt $receipt): array => [
                'id' => 'receipt-'.$receipt->id,
                'source' => 'finance',
                'type' => 'payment_received',
                'description' => 'Payment received against '.$receipt->receipt_no,
                'properties' => ['receipt_no' => $receipt->receipt_no, 'amount' => round((float) $receipt->amount, 2)],
                'causer_name' => null,
                'occurred_at' => $this->toIso($receipt->receipt_date),
                'sort_key' => $this->toIso($receipt->receipt_date) ?? '',
            ]);

        return $invoices->concat($receipts);
    }

    private function toIso(?string $date): ?string
    {
        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }
}
