<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\Attendance;
use App\Models\Employee;
use App\Enums\AttendanceStatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AttendanceResource;
use App\Http\Requests\Api\Admin\AttendanceRequest;

class AttendanceController extends Controller
{
    /**
     * @Permissions("list_attendance", group="hr", desc="List Attendance")
     */
    public function index(Request $request)
    {
        $attendances = Attendance::with('employee')
            ->filter($request->all())
            ->orderBy('date', 'desc')
            ->paginate($request->limit ?? 25);

        return AttendanceResource::collection($attendances);
    }

    /**
     * @Permissions("create_attendance", group="hr", desc="Create Attendance")
     */
    public function store(AttendanceRequest $request)
    {
        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => $request->date],
            $request->validated()
        );

        return response()->json([
            'data' => AttendanceResource::make($attendance->load('employee')),
            'message' => 'Attendance Saved Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_attendance", group="hr", desc="Show Attendance")
     */
    public function show(Attendance $attendance)
    {
        return AttendanceResource::make($attendance->load('employee'));
    }

    /**
     * @Permissions("edit_attendance", group="hr", desc="Edit Attendance")
     */
    public function update(AttendanceRequest $request, Attendance $attendance)
    {
        $attendance->update($request->validated());

        return response()->json([
            'data' => AttendanceResource::make($attendance->load('employee')),
            'message' => 'Attendance Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_attendance", group="hr", desc="Delete Attendance")
     */
    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json([
            'message' => 'Attendance Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("list_attendance", group="hr", desc="Monthly Attendance Sheet")
     */
    public function monthly(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $employees = Employee::with(['attendances' => function ($q) use ($month, $year) {
            $q->whereMonth('date', $month)->whereYear('date', $year);
        }])->get();

        return response()->json([
            'data' => $employees->map(fn ($emp) => [
                'employee' => [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'employee_code' => $emp->employee_code,
                ],
                'attendances' => $emp->attendances->keyBy(fn ($a) => $a->date->format('d')),
            ]),
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * @Permissions("create_attendance", group="hr", desc="Bulk Attendance")
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date'],
            'attendances' => ['required', 'array'],
            'attendances.*.employee_id' => ['required', 'exists:employees,id'],
            'attendances.*.status' => ['required'],
            'attendances.*.check_in' => ['nullable', 'date_format:H:i'],
            'attendances.*.check_out' => ['nullable', 'date_format:H:i'],
            'attendances.*.note' => ['nullable', 'string'],
        ]);

        $date = $request->date;

        foreach ($request->attendances as $row) {
            Attendance::updateOrCreate(
                ['employee_id' => $row['employee_id'], 'date' => $date],
                array_merge($row, ['date' => $date])
            );
        }

        return response()->json(['message' => 'Attendance Saved Successfully']);
    }
}
