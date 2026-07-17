<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\OpeningStockEntry;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\OpeningStockEntryService;
use App\Http\Resources\Admin\Inventory\OpeningStockEntryResource;
use App\Http\Requests\Api\Admin\Inventory\OpeningStockEntryRequest;

class OpeningStockEntryController extends Controller
{
    public function __construct(
        private OpeningStockEntryService $openingStockEntryService,
    ) {}

    #[Permissions('list_opening_stock_entry', group: 'opening_stock_entry', desc: 'List Opening Stock Entry')]
    public function index(Request $request)
    {
        $query = OpeningStockEntry::with([
            'warehouse',
            'openingStockEntryItems.productVariant.product',
        ])
            ->orderByDesc('date');

        if (! empty($request->search)) {
            $key = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($key) {
                $q->where('reference_no', 'like', $key)
                    ->orWhereHas('openingStockEntryItems.productVariant.product', function ($pq) use ($key) {
                        $pq->where('name', 'like', $key)
                            ->orWhere('code', 'like', $key);
                    })
                    ->orWhereHas('openingStockEntryItems.productVariant', function ($vq) use ($key) {
                        $vq->where('sku', 'like', $key);
                    });
            });
        }

        $entries = $query->paginate($request->limit ?? 25);

        return OpeningStockEntryResource::collection($entries);
    }

    #[Permissions('create_opening_stock_entry', group: 'opening_stock_entry', desc: 'Create Opening Stock Entry')]
    public function store(OpeningStockEntryRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;

        try {
            $entry = DB::transaction(function () use ($formData, $user, $status) {
                $entry = OpeningStockEntry::create([
                    'reference_no' => $formData['reference_no'] ?? null,
                    'date' => $formData['date'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'remarks' => $formData['remarks'] ?? null,
                    'create_user_id' => $user->id,
                    'status' => StatusEnum::DRAFT,
                ]);

                $entry->openingStockEntryItems()->createMany(
                    collect($formData['items'])->map(fn (array $item) => [
                        'product_variant_id' => $item['product_variant_id'],
                        'unit_id' => $item['unit_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                        'batch_no' => $item['batch_no'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ])->all()
                );

                if ($status === StatusEnum::APPROVED->value) {
                    $this->openingStockEntryService->approve($entry->fresh(['openingStockEntryItems.productVariant.product']), $user);
                }

                return $entry;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $entry->load(['warehouse', 'openingStockEntryItems.productVariant.product', 'openingStockEntryItems.unit']);

        return response()->json([
            'data' => OpeningStockEntryResource::make($entry),
            'message' => 'Opening Stock Entry Added Successfully',
        ], 201);
    }

    #[Permissions('show_opening_stock_entry', group: 'opening_stock_entry', desc: 'Show Opening Stock Entry')]
    public function show(OpeningStockEntry $openingStockEntry)
    {
        $openingStockEntry->load([
            'warehouse',
            'openingStockEntryItems.productVariant.product',
            'openingStockEntryItems.unit',
            'openingStockEntryItems.batch',
        ]);

        return OpeningStockEntryResource::make($openingStockEntry);
    }

    #[Permissions('edit_opening_stock_entry', group: 'opening_stock_entry', desc: 'Edit Opening Stock Entry')]
    public function update(OpeningStockEntryRequest $request, OpeningStockEntry $openingStockEntry)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $wasApproved = $openingStockEntry->status === StatusEnum::APPROVED;

        try {
            $openingStockEntry = DB::transaction(function () use ($openingStockEntry, $formData, $user, $wasApproved) {
                if ($wasApproved) {
                    $this->openingStockEntryService->reverseApprovedOpeningStock($openingStockEntry, $user);
                    $openingStockEntry->refresh();
                }

                $openingStockEntry->update([
                    'reference_no' => $formData['reference_no'] ?? null,
                    'date' => $formData['date'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'remarks' => $formData['remarks'] ?? null,
                ]);

                $openingStockEntry->openingStockEntryItems()->delete();

                $openingStockEntry->openingStockEntryItems()->createMany(
                    collect($formData['items'])->map(fn (array $item) => [
                        'product_variant_id' => $item['product_variant_id'],
                        'unit_id' => $item['unit_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                        'batch_no' => $item['batch_no'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ])->all()
                );

                if ($wasApproved) {
                    $this->openingStockEntryService->approve(
                        $openingStockEntry->fresh(['openingStockEntryItems.productVariant.product']),
                        $user,
                    );
                }

                return $openingStockEntry;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $openingStockEntry->load(['warehouse', 'openingStockEntryItems.productVariant.product', 'openingStockEntryItems.unit']);

        return response()->json([
            'data' => OpeningStockEntryResource::make($openingStockEntry),
            'message' => 'Opening Stock Entry Updated Successfully',
        ]);
    }

    #[Permissions('delete_opening_stock_entry', group: 'opening_stock_entry', desc: 'Delete Opening Stock Entry')]
    public function destroy(OpeningStockEntry $openingStockEntry)
    {
        if ($openingStockEntry->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved opening stock entries cannot be deleted.',
            ], 422);
        }

        DB::transaction(function () use ($openingStockEntry) {
            $openingStockEntry->openingStockEntryItems()->delete();
            $openingStockEntry->delete();
        });

        return response()->json([
            'message' => 'Opening Stock Entry Deleted Successfully',
        ]);
    }

    #[Permissions('approve_opening_stock_entry', group: 'opening_stock_entry', desc: 'Approve Opening Stock Entry')]
    public function approve(OpeningStockEntry $openingStockEntry)
    {
        if ($openingStockEntry->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => OpeningStockEntryResource::make($openingStockEntry),
                'message' => 'Opening Stock Entry Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($openingStockEntry, $user) {
                $this->openingStockEntryService->approve(
                    $openingStockEntry->fresh(['openingStockEntryItems.productVariant.product']),
                    $user,
                );
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $openingStockEntry->load(['warehouse', 'openingStockEntryItems.productVariant.product', 'openingStockEntryItems.unit']);

        return response()->json([
            'data' => OpeningStockEntryResource::make($openingStockEntry->fresh()),
            'message' => 'Opening Stock Entry Approved Successfully',
        ]);
    }
}
