<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\Employee;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HR\EmployeeResource;
use App\Http\Requests\Api\Admin\HR\EmployeeRequest;

class EmployeeController extends Controller
{
    /**
     * @Permissions("list_employee", group="employee", desc="List Employee")
     */
    public function index(Request $request)
    {
        $employees = Employee::with(['department', 'designation'])
            ->filter($request->all())
            ->paginate($request->limit ?? 25);

        return EmployeeResource::collection($employees);
    }

    /**
     * @Permissions("create_employee", group="employee", desc="Create Employee")
     */
    public function store(EmployeeRequest $request)
    {
        $employee = Employee::create($request->validated());

        return response()->json([
            'data' => EmployeeResource::make($employee->load(['department', 'designation'])),
            'message' => 'Employee Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_employee", group="employee", desc="Show Employee")
     */
    public function show(Employee $employee)
    {
        return EmployeeResource::make($employee->load(['department', 'designation']));
    }

    /**
     * @Permissions("edit_employee", group="employee", desc="Edit Employee")
     */
    public function update(EmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return response()->json([
            'data' => EmployeeResource::make($employee->load(['department', 'designation'])),
            'message' => 'Employee Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_employee", group="employee", desc="Delete Employee")
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json([
            'message' => 'Employee Deleted Successfully',
        ]);
    }
}
