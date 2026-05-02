<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HR\LeaveTypeResource;
use App\Http\Requests\Api\Admin\HR\LeaveTypeRequest;

class LeaveTypeController extends Controller
{
    /**
     * @Permissions("list_leave_type", group="leave_type", desc="List Leave Type")
     */
    public function index(Request $request)
    {
        $leaveTypes = LeaveType::paginate($request->limit ?? 25);

        return LeaveTypeResource::collection($leaveTypes);
    }

    /**
     * @Permissions("create_leave_type", group="leave_type", desc="Create Leave Type")
     */
    public function store(LeaveTypeRequest $request)
    {
        $leaveType = LeaveType::create($request->validated());

        return response()->json([
            'data' => LeaveTypeResource::make($leaveType),
            'message' => 'Leave Type Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_leave_type", group="leave_type", desc="Show Leave Type")
     */
    public function show(LeaveType $leaveType)
    {
        return LeaveTypeResource::make($leaveType);
    }

    /**
     * @Permissions("edit_leave_type", group="leave_type", desc="Edit Leave Type")
     */
    public function update(LeaveTypeRequest $request, LeaveType $leaveType)
    {
        $leaveType->update($request->validated());

        return response()->json([
            'data' => LeaveTypeResource::make($leaveType),
            'message' => 'Leave Type Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_leave_type", group="leave_type", desc="Delete Leave Type")
     */
    public function destroy(LeaveType $leaveType)
    {
        $leaveType->delete();

        return response()->json([
            'message' => 'Leave Type Deleted Successfully',
        ]);
    }
}
