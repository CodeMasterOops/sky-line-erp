<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Tax;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TaxResource;
use App\Http\Requests\Api\Admin\TaxRequest;

class TaxController extends Controller
{
    /**
     * @Permissions("list_tax", group="tax", desc="List Tax")
     *
     * Pass ?for=invoice or ?for=bill to get only VAT-type taxes suitable for line items.
     */
    public function index(Request $request)
    {
        $query = Tax::query();

        if (in_array($request->query('for'), ['invoice', 'bill', 'line_item'])) {
            $query->lineItem();
        }

        $taxes = $query->get();

        return TaxResource::collection($taxes);
    }

    /**
     * @Permissions("create_tax", group="tax", desc="Create Tax")
     */
    public function store(TaxRequest $request)
    {
        $tax = Tax::create($request->validated());

        return response()->json([
            'data' => TaxResource::make($tax),
            'message' => 'Tax Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_tax", group="tax", desc="Show Tax")
     */
    public function show(Tax $tax)
    {
        return TaxResource::make($tax);
    }

    /**
     * @Permissions("edit_tax", group="tax", desc="Edit Tax")
     */
    public function update(TaxRequest $request, Tax $tax)
    {
        if ($tax->is_system) {
            return response()->json([
                'message' => 'System tax rates are managed by the SaaS administrator and cannot be edited.',
            ], 403);
        }

        $tax->update($request->validated());

        return response()->json([
            'data' => TaxResource::make($tax),
            'message' => 'Tax Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_tax", group="tax", desc="Delete Tax")
     */
    public function destroy(Tax $tax)
    {
        if ($tax->is_system) {
            return response()->json([
                'message' => 'System tax rates are managed by the SaaS administrator and cannot be deleted.',
            ], 403);
        }

        $tax->delete();

        return response()->json([
            'message' => 'Tax Deleted Successfully',
        ]);
    }
}
