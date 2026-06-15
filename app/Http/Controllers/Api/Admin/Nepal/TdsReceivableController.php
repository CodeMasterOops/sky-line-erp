<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use Illuminate\Http\Request;
use App\Models\TdsReceivable;
use App\Annotation\Permissions;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Nepal\TdsReceivableService;
use App\Http\Requests\Api\Admin\Nepal\TdsReceivableRequest;

class TdsReceivableController extends Controller
{
    public function __construct(private TdsReceivableService $service) {}

    /**
     * @Permissions("list_tds_receivable", group="tds_receivable", desc="List TDS Receivables")
     */
    public function index(Request $request): JsonResponse
    {
        $rows = TdsReceivable::query()
            ->with('party:id,name,pan', 'createUser:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('party_id'), fn ($q) => $q->where('party_id', $request->party_id))
            ->when($request->filled('period_month'), fn ($q) => $q->where('period_month', $request->period_month))
            ->latest()
            ->paginate(25);

        return response()->json(['data' => $rows]);
    }

    /**
     * @Permissions("create_tds_receivable", group="tds_receivable", desc="Record TDS Certificate")
     */
    public function store(TdsReceivableRequest $request): JsonResponse
    {
        $receivable = $this->service->record($request->validated());

        return response()->json(['data' => $receivable->load('party:id,name,pan')], 201);
    }

    /**
     * @Permissions("view_tds_receivable", group="tds_receivable", desc="View TDS Receivable")
     */
    public function show(TdsReceivable $tdsReceivable): JsonResponse
    {
        return response()->json([
            'data' => $tdsReceivable->load('party:id,name,pan', 'tdsDeduction', 'settlementJournal'),
        ]);
    }

    /**
     * @Permissions("approve_tds_receivable", group="tds_receivable", desc="Approve TDS Receivable")
     */
    public function approve(TdsReceivable $tdsReceivable): JsonResponse
    {
        $this->service->approve($tdsReceivable);

        return response()->json(['data' => $tdsReceivable->refresh()]);
    }

    /**
     * @Permissions("settle_tds_receivable", group="tds_receivable", desc="Settle TDS Receivable")
     */
    public function settle(Request $request, TdsReceivable $tdsReceivable): JsonResponse
    {
        $request->validate([
            'settled_at' => ['nullable', 'date'],
        ]);

        $this->service->settle($tdsReceivable, $request->settled_at);

        return response()->json(['data' => $tdsReceivable->refresh()->load('settlementJournal')]);
    }
}
