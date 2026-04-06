<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\SalesOrder;
use App\Enums\StatusEnum;
use App\Tenancy\TRule;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SalesOrderResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Requests\Api\Admin\SalesOrderRequest;
use App\Models\Invoice;

class SalesOrderController extends Controller
{
    /**
     * @Permissions("list_sales_order", group="sales_order", desc="List Sales Order")
     */
    public function index(Request $request)
    {
        $orders = SalesOrder::filter($request->all())
            ->with(['party', 'salesOrderItems'])
            ->withCount(['invoices'])
            ->latest('order_date')
            ->paginate($request->limit ?? 25);

        return SalesOrderResource::collection($orders);
    }

    /**
     * @Permissions("create_sales_order", group="sales_order", desc="Create Sales Order")
     */
    public function store(SalesOrderRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $orderNo = $formData['order_no'] ?? $this->generateOrderNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $order = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $orderNo) {
            $order = SalesOrder::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'quotation_id' => $formData['quotation_id'] ?? null,
                'order_no' => $orderNo,
                'order_date' => $formData['order_date'],
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $order->salesOrderItems()->createMany($items);

            return $order;
        });

        $order->load([
            'party',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return response()->json([
            'data' => SalesOrderResource::make($order),
            'message' => 'Sales Order Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_sales_order", group="sales_order", desc="Show Sales Order")
     */
    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'party',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return SalesOrderResource::make($salesOrder);
    }

    /**
     * @Permissions("edit_sales_order", group="sales_order", desc="Edit Sales Order")
     */
    public function update(SalesOrderRequest $request, SalesOrder $salesOrder)
    {
        if ($salesOrder->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved sales orders cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();
        $orderNo = $formData['order_no'] ?? $salesOrder->order_no;

        $salesOrder = DB::transaction(function () use ($salesOrder, $formData, $orderNo) {
            $salesOrder->update([
                'party_id' => $formData['party_id'] ?? null,
                'quotation_id' => $formData['quotation_id'] ?? $salesOrder->quotation_id,
                'order_no' => $orderNo,
                'order_date' => $formData['order_date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $salesOrder->salesOrderItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $salesOrder->salesOrderItems()->createMany($items);

            return $salesOrder;
        });

        $salesOrder->load([
            'party',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return response()->json([
            'data' => SalesOrderResource::make($salesOrder),
            'message' => 'Sales Order Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_sales_order", group="sales_order", desc="Delete Sales Order")
     */
    public function destroy(SalesOrder $salesOrder)
    {
        $salesOrder->salesOrderItems()->delete();
        $salesOrder->delete();

        return response()->json([
            'message' => 'Sales Order Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_sales_order", group="sales_order", desc="Approve Sales Order")
     */
    public function approve(SalesOrder $salesOrder)
    {
        if ($salesOrder->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => SalesOrderResource::make($salesOrder),
                'message' => 'Sales Order Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $salesOrder->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $salesOrder->load([
            'party',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return response()->json([
            'data' => SalesOrderResource::make($salesOrder),
            'message' => 'Sales Order Approved Successfully',
        ]);
    }

    /**
     * @Permissions("convert_sales_order_to_invoice", group="sales_order", desc="Convert Sales Order To Invoice")
     */
    public function convertToInvoice(Request $request, SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved sales orders can be converted to invoice.',
            ], 422);
        }

        if ($salesOrder->invoices()->exists()) {
            return response()->json([
                'message' => 'Sales order already converted to invoice.',
            ], 422);
        }

        $data = $request->validate([
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'due_date' => ['nullable', 'date'],
        ]);

        $salesOrder->loadMissing([
            'salesOrderItems',
            'party',
        ]);

        $user = auth('admin')->user();
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $invoiceNo = $this->generateInvoiceNo($fiscalYearId, $setting->fiscalYear?->year_code);
        $invoiceDate = now()->toDateString();

        $invoice = DB::transaction(function () use ($salesOrder, $user, $fiscalYearId, $invoiceNo, $invoiceDate, $data) {
            $invoice = Invoice::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $salesOrder->party_id,
                'reference_type' => $salesOrder::class,
                'reference_id' => $salesOrder->id,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => $data['due_date'] ?? null,
                'remarks' => $salesOrder->remarks,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => null,
                'status' => StatusEnum::DRAFT->value,
            ]);

            $items = $salesOrder->salesOrderItems->map(function ($item) use ($data) {
                return [
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $data['warehouse_id'],
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'tax_id' => $item->tax_id,
                    'tax_amount' => $item->tax_amount ?? 0,
                    'discount_amount' => $item->discount_amount ?? 0,
                ];
            })->all();

            $invoice->invoiceItems()->createMany($items);

            return $invoice;
        });

        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
        ]);

        return response()->json([
            'data' => InvoiceResource::make($invoice),
            'message' => 'Invoice created from sales order successfully.',
        ], 201);
    }

    private function generateOrderNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = SalesOrder::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'SO-'.($count + 1).$suffix;
    }

    private function generateInvoiceNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Invoice::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'INV-'.($count + 1).$suffix;
    }
}
