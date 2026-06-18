<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\User;
use App\Models\Company;
use App\Enums\StatusEnum;
use App\Models\DamageReport;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Http\Resources\Admin\Inventory\DamageReportResource;
use App\Http\Requests\Api\Admin\Inventory\DamageReportRequest;

class DamageReportController extends Controller
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
    ) {}

    #[Permissions('list_damage_report', group: 'damage_report', desc: 'List Damage Report')]
    public function index(Request $request)
    {
        $query = DamageReport::with(['warehouse'])
            ->orderByDesc('date');

        if (! empty($request->search)) {
            $key = '%'.trim($request->search).'%';
            $query->where('reference_no', 'like', $key);
        }

        return DamageReportResource::collection($query->paginate($request->limit ?? 25));
    }

    #[Permissions('create_damage_report', group: 'damage_report', desc: 'Create Damage Report')]
    public function store(DamageReportRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;

        try {
            $report = DB::transaction(function () use ($formData, $user, $status) {
                $report = DamageReport::create([
                    'reference_no' => $formData['reference_no'] ?? null,
                    'date' => $formData['date'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'reason' => $formData['reason'] ?? null,
                    'remarks' => $formData['remarks'] ?? null,
                    'create_user_id' => $user->id,
                    'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                    'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                    'status' => $status,
                ]);

                $report->damageReportItems()->createMany($formData['items']);

                if ($status === StatusEnum::APPROVED->value) {
                    $this->applyApprovalEffects($report, $user);
                }

                return $report;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $report->load(['warehouse', 'damageReportItems.productVariant.product', 'damageReportItems.unit']);

        return response()->json([
            'data' => DamageReportResource::make($report),
            'message' => 'Damage Report Created Successfully',
        ], 201);
    }

    #[Permissions('show_damage_report', group: 'damage_report', desc: 'Show Damage Report')]
    public function show(DamageReport $damageReport)
    {
        $damageReport->load(['warehouse', 'damageReportItems.productVariant.product', 'damageReportItems.unit']);

        return DamageReportResource::make($damageReport);
    }

    #[Permissions('edit_damage_report', group: 'damage_report', desc: 'Edit Damage Report')]
    public function update(DamageReportRequest $request, DamageReport $damageReport)
    {
        if ($damageReport->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Approved damage reports cannot be edited.'], 422);
        }

        $formData = $request->validated();

        $damageReport = DB::transaction(function () use ($damageReport, $formData) {
            $damageReport->update([
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'warehouse_id' => $formData['warehouse_id'],
                'reason' => $formData['reason'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $damageReport->damageReportItems()->delete();
            $damageReport->damageReportItems()->createMany($formData['items']);

            return $damageReport;
        });

        $damageReport->load(['warehouse', 'damageReportItems.productVariant.product', 'damageReportItems.unit']);

        return response()->json([
            'data' => DamageReportResource::make($damageReport),
            'message' => 'Damage Report Updated Successfully',
        ]);
    }

    #[Permissions('delete_damage_report', group: 'damage_report', desc: 'Delete Damage Report')]
    public function destroy(DamageReport $damageReport)
    {
        if ($damageReport->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved damage reports cannot be deleted. Please create a stock adjustment to reverse the stock effects.',
            ], 422);
        }

        DB::transaction(function () use ($damageReport) {
            $damageReport->damageReportItems()->delete();
            $damageReport->delete();
        });

        return response()->json(['message' => 'Damage Report Deleted Successfully']);
    }

    #[Permissions('approve_damage_report', group: 'damage_report', desc: 'Approve Damage Report')]
    public function approve(DamageReport $damageReport)
    {
        if ($damageReport->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => DamageReportResource::make($damageReport),
                'message' => 'Damage Report Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($damageReport, $user) {
                $damageReport->update([
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $this->applyApprovalEffects($damageReport, $user);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $damageReport->load(['warehouse', 'damageReportItems.productVariant.product', 'damageReportItems.unit']);

        return response()->json([
            'data' => DamageReportResource::make($damageReport),
            'message' => 'Damage Report Approved Successfully',
        ]);
    }

    private function applyApprovalEffects(DamageReport $report, User $user): void
    {
        $report->loadMissing('damageReportItems');
        $company = Company::findOrFail($report->company_id);

        foreach ($report->damageReportItems as $item) {
            $quantity = (int) $item->quantity;
            if ($quantity <= 0) {
                continue;
            }

            $this->inventoryIssue->issue(
                $company,
                $report,
                $item->product_variant_id,
                (int) $report->warehouse_id,
                $quantity,
                ChangeTypeEnum::DAMAGE,
                $user->id,
                $report->remarks,
            );
        }
    }
}
