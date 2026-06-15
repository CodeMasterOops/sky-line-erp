<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\CustomerAdvance;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Sales\CustomerAdvanceService;
use App\Http\Requests\Api\Admin\Sales\CustomerAdvanceRequest;

class CustomerAdvanceController extends Controller
{
    public function __construct(private CustomerAdvanceService $service) {}

    /**
     * @Permissions("list_customer_advance", group="customer_advance", desc="List Customer Advances")
     */
    public function index(Request $request): JsonResponse
    {
        $rows = CustomerAdvance::query()
            ->with('party:id,name,pan', 'account:id,name', 'createUser:id,name')
            ->when($request->filled('party_id'), fn ($q) => $q->where('party_id', $request->party_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->boolean('unapplied'), fn ($q) => $q->whereColumn('applied_amount', '<', 'amount'))
            ->latest()
            ->paginate(25);

        return response()->json(['data' => $rows]);
    }

    /**
     * @Permissions("create_customer_advance", group="customer_advance", desc="Record Customer Advance")
     */
    public function store(CustomerAdvanceRequest $request): JsonResponse
    {
        $advance = $this->service->create($request->validated());

        return response()->json(['data' => $advance->load('party:id,name', 'account:id,name')], 201);
    }

    /**
     * @Permissions("view_customer_advance", group="customer_advance", desc="View Customer Advance")
     */
    public function show(CustomerAdvance $customerAdvance): JsonResponse
    {
        return response()->json([
            'data' => $customerAdvance->load('party:id,name,pan', 'account:id,name', 'applications.invoice:id,invoice_no'),
        ]);
    }

    /**
     * @Permissions("approve_customer_advance", group="customer_advance", desc="Approve Customer Advance")
     */
    public function approve(CustomerAdvance $customerAdvance): JsonResponse
    {
        $this->service->approve($customerAdvance);

        return response()->json(['data' => $customerAdvance->refresh()]);
    }

    /**
     * @Permissions("apply_customer_advance", group="customer_advance", desc="Apply Advance to Invoice")
     */
    public function apply(Request $request, CustomerAdvance $customerAdvance): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'applied_at' => ['nullable', 'date'],
        ]);

        $invoice = Invoice::withoutGlobalScopes()->findOrFail($data['invoice_id']);

        $application = $this->service->apply(
            $customerAdvance,
            $invoice,
            (float) $data['amount'],
            $data['applied_at'] ?? null,
        );

        return response()->json(['data' => $application->load('invoice:id,invoice_no')], 201);
    }

    /**
     * @Permissions("void_customer_advance", group="customer_advance", desc="Void Customer Advance")
     */
    public function void(CustomerAdvance $customerAdvance): JsonResponse
    {
        $this->service->void($customerAdvance);

        return response()->json(['data' => $customerAdvance->refresh()]);
    }
}
