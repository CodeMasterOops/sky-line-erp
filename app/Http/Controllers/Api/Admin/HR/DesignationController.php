<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\Designation;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HR\DesignationResource;
use App\Http\Requests\Api\Admin\HR\DesignationRequest;

class DesignationController extends Controller
{
    /**
     * @Permissions("list_designation", group="designation", desc="List Designation")
     */
    public function index(Request $request)
    {
        $designations = Designation::filter($request->all())
            ->paginate($request->limit ?? 25);

        return DesignationResource::collection($designations);
    }

    /**
     * @Permissions("create_designation", group="designation", desc="Create Designation")
     */
    public function store(DesignationRequest $request)
    {
        $designation = Designation::create($request->validated());

        return response()->json([
            'data' => DesignationResource::make($designation),
            'message' => 'Designation Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_designation", group="designation", desc="Show Designation")
     */
    public function show(Designation $designation)
    {
        return DesignationResource::make($designation);
    }

    /**
     * @Permissions("edit_designation", group="designation", desc="Edit Designation")
     */
    public function update(DesignationRequest $request, Designation $designation)
    {
        $designation->update($request->validated());

        return response()->json([
            'data' => DesignationResource::make($designation),
            'message' => 'Designation Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_designation", group="designation", desc="Delete Designation")
     */
    public function destroy(Designation $designation)
    {
        $designation->delete();

        return response()->json([
            'message' => 'Designation Deleted Successfully',
        ]);
    }
}
