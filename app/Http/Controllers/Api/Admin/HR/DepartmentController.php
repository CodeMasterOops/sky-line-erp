<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\Department;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DepartmentResource;
use App\Http\Requests\Api\Admin\DepartmentRequest;

class DepartmentController extends Controller
{
    /**
     * @Permissions("list_department", group="hr", desc="List Department")
     */
    public function index(Request $request)
    {
        $departments = Department::filter($request->all())
            ->paginate($request->limit ?? 25);

        return DepartmentResource::collection($departments);
    }

    /**
     * @Permissions("create_department", group="hr", desc="Create Department")
     */
    public function store(DepartmentRequest $request)
    {
        $department = Department::create($request->validated());

        return response()->json([
            'data' => DepartmentResource::make($department),
            'message' => 'Department Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_department", group="hr", desc="Show Department")
     */
    public function show(Department $department)
    {
        return DepartmentResource::make($department);
    }

    /**
     * @Permissions("edit_department", group="hr", desc="Edit Department")
     */
    public function update(DepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());

        return response()->json([
            'data' => DepartmentResource::make($department),
            'message' => 'Department Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_department", group="hr", desc="Delete Department")
     */
    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json([
            'message' => 'Department Deleted Successfully',
        ]);
    }
}
