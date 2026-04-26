<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SerialNumber;
use Illuminate\Http\Request;
use App\Annotation\Permissions;

class SerialNumberController extends Controller
{
    /**
     * @Permissions("list_serial_number", group="serial_number", desc="List Serial Numbers")
     */
    public function index(Request $request)
    {
        $company = auth('admin')->user()->company;

        $query = SerialNumber::with(['productVariant.product', 'warehouse'])
            ->where('company_id', $company->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('batch_no')) {
            $query->where('batch_no', 'like', '%'.$request->input('batch_no').'%');
        }

        if ($request->filled('search')) {
            $query->where('serial_no', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('product_id')) {
            $query->whereHas('productVariant.product', fn ($q) => $q->where('products.id', $request->input('product_id')));
        }

        if ($request->filled('expiry_within_days')) {
            $days = (int) $request->input('expiry_within_days');
            $query->whereNotNull('expiry_date')
                  ->whereDate('expiry_date', '<=', now()->addDays($days))
                  ->whereDate('expiry_date', '>=', now());
        }

        $serials = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25));

        // Flatten product_name and source for frontend
        $serials->getCollection()->transform(function ($sn) {
            $sn->product_name = $sn->productVariant?->product?->name ?? '-';
            $sn->source = $sn->receiptMovement ? 'GRN / Receipt' : '-';
            return $sn;
        });

        return response()->json($serials);
    }

    /**
     * @Permissions("view_serial_number", group="serial_number", desc="View Serial Number")
     */
    public function show(SerialNumber $serialNumber)
    {
        $serialNumber->load(['productVariant.product', 'warehouse', 'receiptMovement', 'issueMovement']);
        $serialNumber->product_name = $serialNumber->productVariant?->product?->name ?? '-';
        return response()->json($serialNumber);
    }
}
