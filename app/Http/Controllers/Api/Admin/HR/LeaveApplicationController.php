<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\LeaveApplication;
use App\Enums\LeaveStatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LeaveApplicationResource;
use App\Http\Requests\Api\Admin\LeaveApplicationRequest;

class LeaveApplicationController extends Controller
{
    /**
     * @Permissions("list_leave_application", group="hr", desc="List Leave Applications")
     */
    public function index(Request $request)
    {
        $applications = LeaveApplication::with(['employee', 'leaveType'])
            ->filter($request->all())
            ->latest()
            ->paginate($request->limit ?? 25);

        return LeaveApplicationResource::collection($applications);
    }

    /**
     * @Permissions("create_leave_application", group="hr", desc="Create Leave Application")
     */
    public function store(LeaveApplicationRequest $request)
    {
        $application = LeaveApplication::create($request->validated());

        return response()->json([
            'data' => LeaveApplicationResource::make($application->load(['employee', 'leaveType'])),
            'message' => 'Leave Application Submitted Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_leave_application", group="hr", desc="Show Leave Application")
     */
    public function show(LeaveApplication $leaveApplication)
    {
        return LeaveApplicationResource::make($leaveApplication->load(['employee', 'leaveType']));
    }

    /**
     * @Permissions("edit_leave_application", group="hr", desc="Edit Leave Application")
     */
    public function update(LeaveApplicationRequest $request, LeaveApplication $leaveApplication)
    {
        abort_if($leaveApplication->status !== LeaveStatusEnum::PENDING, 403, 'Only pending applications can be edited.');

        $leaveApplication->update($request->validated());

        return response()->json([
            'data' => LeaveApplicationResource::make($leaveApplication->load(['employee', 'leaveType'])),
            'message' => 'Leave Application Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_leave_application", group="hr", desc="Delete Leave Application")
     */
    public function destroy(LeaveApplication $leaveApplication)
    {
        $leaveApplication->delete();

        return response()->json([
            'message' => 'Leave Application Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("edit_leave_application", group="hr", desc="Approve Leave Application")
     */
    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        abort_if($leaveApplication->status !== LeaveStatusEnum::PENDING, 403, 'Only pending applications can be approved.');

        $leaveApplication->update([
            'status' => LeaveStatusEnum::APPROVED,
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'data' => LeaveApplicationResource::make($leaveApplication->load(['employee', 'leaveType'])),
            'message' => 'Leave Application Approved',
        ]);
    }

    /**
     * @Permissions("edit_leave_application", group="hr", desc="Reject Leave Application")
     */
    public function reject(Request $request, LeaveApplication $leaveApplication)
    {
        abort_if($leaveApplication->status !== LeaveStatusEnum::PENDING, 403, 'Only pending applications can be rejected.');

        $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);

        $leaveApplication->update([
            'status' => LeaveStatusEnum::REJECTED,
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'data' => LeaveApplicationResource::make($leaveApplication->load(['employee', 'leaveType'])),
            'message' => 'Leave Application Rejected',
        ]);
    }
}
