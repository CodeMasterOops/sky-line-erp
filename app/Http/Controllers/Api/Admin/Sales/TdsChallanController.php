<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Models\Party;
use App\Models\FiscalYear;
use App\Models\TdsChallan;
use App\Models\TdsDeduction;
use Illuminate\Http\Request;
use App\Enums\TdsCategoryEnum;
use App\Annotation\Permissions;
use App\Services\Sales\TdsService;
use App\Enums\TdsChallanStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Admin\Sales\TdsChallanResource;
use App\Http\Requests\Api\Admin\Sales\TdsChallanRequest;

class TdsChallanController extends Controller
{
    public function __construct(
        private readonly TdsService $tdsService,
    ) {}

    /**
     * @Permissions("list_tds_deductions", group="tds_challan", desc="List TDS Deductions")
     */
    public function listDeductions(Request $request)
    {
        $query = TdsDeduction::withoutGlobalScopes()
            ->where('company_id', auth('admin')->user()->company_id)
            ->when($request->party_id, fn ($q) => $q->where('party_id', $request->party_id))
            ->when($request->period_month, fn ($q) => $q->where('period_month', $request->period_month))
            ->when($request->boolean('unbundled'), fn ($q) => $q->whereDoesntHave('challanItem'))
            ->latest();

        $deductions = $query->paginate($request->integer('limit', 100));

        $items = $deductions->getCollection()->map(function (TdsDeduction $d) {
            $category = $d->tds_category instanceof TdsCategoryEnum
                ? $d->tds_category
                : TdsCategoryEnum::tryFrom((string) $d->tds_category);

            return [
                'id' => $d->id,
                'party_id' => $d->party_id,
                'tds_category' => $category?->value,
                'tds_category_label' => $category?->label() ?? '',
                'base_amount' => $d->base_amount,
                'tds_rate' => $d->tds_rate,
                'tds_amount' => $d->tds_amount,
                'period_month' => $d->period_month,
                'created_at_date' => $d->created_at?->toDateString(),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $deductions->total(),
                'per_page' => $deductions->perPage(),
                'current_page' => $deductions->currentPage(),
            ],
        ]);
    }

    /**
     * @Permissions("list_tds_challan", group="tds_challan", desc="List TDS Challans")
     */
    public function index(Request $request)
    {
        $challans = TdsChallan::with(['party'])
            ->when($request->party_id, fn ($q) => $q->where('party_id', $request->party_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->period_month, fn ($q) => $q->where('period_month', $request->period_month))
            ->latest('challan_date')
            ->paginate($request->limit ?? 25);

        return TdsChallanResource::collection($challans);
    }

    /**
     * @Permissions("create_tds_challan", group="tds_challan", desc="Create TDS Challan")
     */
    public function store(TdsChallanRequest $request)
    {
        $user = auth('admin')->user();
        $data = $request->validated();

        $challan = $this->tdsService->createChallan(
            companyId: $user->company_id,
            fiscalYearId: $user->company->fiscal_year_id,
            partyId: $data['party_id'],
            month: $data['period_month'],
            deductionIds: $data['deduction_ids'],
            attributes: $data,
        );

        $challan->load(['party', 'items']);

        return response()->json([
            'data' => TdsChallanResource::make($challan),
            'message' => 'TDS Challan created successfully.',
        ], 201);
    }

    /**
     * @Permissions("show_tds_challan", group="tds_challan", desc="Show TDS Challan")
     */
    public function show(TdsChallan $tdsChallan)
    {
        $tdsChallan->load(['party', 'items.tdsDeduction']);

        return TdsChallanResource::make($tdsChallan);
    }

    /**
     * @Permissions("submit_tds_challan", group="tds_challan", desc="Submit TDS Challan")
     */
    public function markSubmitted(TdsChallan $tdsChallan)
    {
        if ($tdsChallan->status === TdsChallanStatusEnum::Submitted) {
            return response()->json(['message' => 'Challan is already submitted.'], 422);
        }

        $tdsChallan->update(['status' => TdsChallanStatusEnum::Submitted]);

        return response()->json([
            'data' => TdsChallanResource::make($tdsChallan),
            'message' => 'TDS Challan marked as submitted.',
        ]);
    }

    /**
     * @Permissions("view_tds_certificate", group="tds_challan", desc="View TDS Certificate")
     */
    public function generateCertificate(Party $party, int $month)
    {
        $fy = FiscalYear::find(auth('admin')->user()->company->fiscal_year_id);

        if (! $fy) {
            throw ValidationException::withMessages([
                'fiscal_year' => 'No active fiscal year found.',
            ]);
        }

        $certificate = $this->tdsService->generateCertificate($party, $month, $fy);

        return response()->json(['data' => $certificate]);
    }
}
