<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Tax;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TaxResource;
use App\Http\Requests\Api\Admin\TaxRequest;

class TaxController extends Controller
{
    /**
     * @Permissions("list_tax", group="tax", desc="List Tax")
     */
    public function index()
    {
        $taxes = Tax::all();

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
        $tax->delete();

        return response()->json([
            'message' => 'Tax Deleted Successfully',
        ]);
    }
}
