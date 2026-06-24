<?php

namespace App\Http\Controllers\Api\Admin\HR;

use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Nepal\NepaliDateService;
use App\Http\Resources\Admin\HR\AttendanceResource;
use App\Http\Requests\Api\Admin\HR\AttendanceRequest;

class AttendanceController extends Controller
{
    #[Permissions('list_attendance', group: 'attendance', desc: 'List Attendance')]
    public function index(Request $request)
    {
        $attendances = Attendance::with('employee')
            ->filter($request->all())
            ->orderBy('date', 'desc')
            ->paginate($request->limit ?? 25);

        return AttendanceResource::collection($attendances);
    }

    #[Permissions('create_attendance', group: 'attendance', desc: 'Create Attendance')]
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

    #[Permissions('show_attendance', group: 'attendance', desc: 'Show Attendance')]
    public function show(Attendance $attendance)
    {
        return AttendanceResource::make($attendance->load('employee'));
    }

    #[Permissions('edit_attendance', group: 'attendance', desc: 'Edit Attendance')]
    public function update(AttendanceRequest $request, Attendance $attendance)
    {
        $attendance->update($request->validated());

        return response()->json([
            'data' => AttendanceResource::make($attendance->load('employee')),
            'message' => 'Attendance Updated Successfully',
        ]);
    }

    #[Permissions('delete_attendance', group: 'attendance', desc: 'Delete Attendance')]
    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json([
            'message' => 'Attendance Deleted Successfully',
        ]);
    }

    #[Permissions('list_attendance', group: 'attendance', desc: 'Monthly Attendance Sheet')]
    public function monthly(Request $request, NepaliDateService $nepaliDate)
    {
        $today = $nepaliDate->today();
        $bsYear = (int) ($request->bs_year ?? $today['year']);
        $bsMonth = (int) ($request->bs_month ?? $today['month']);

        $daysInMonth = $nepaliDate->daysInBsMonth($bsYear, $bsMonth);
        $start = $nepaliDate->bsToAd($bsYear, $bsMonth, 1)->startOfDay();
        $end = $nepaliDate->bsToAd($bsYear, $bsMonth, $daysInMonth)->endOfDay();

        $days = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $ad = $nepaliDate->bsToAd($bsYear, $bsMonth, $day);
            $days[] = [
                'bs_day' => str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'ad_date' => $ad->toDateString(),
                'weekday' => $ad->isoFormat('dd'),
                'is_weekend' => $ad->dayOfWeek === Carbon::SATURDAY,
            ];
        }

        $employees = Employee::with(['attendances' => function ($q) use ($start, $end) {
            $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        }])->get();

        return response()->json([
            'data' => $employees->map(fn ($emp) => [
                'employee' => [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'employee_code' => $emp->employee_code,
                ],
                'attendances' => $emp->attendances->keyBy(
                    fn ($a) => str_pad((string) $nepaliDate->adToBs($a->date)['day'], 2, '0', STR_PAD_LEFT)
                ),
            ]),
            'bs_year' => $bsYear,
            'bs_month' => $bsMonth,
            'bs_month_name' => $nepaliDate->monthName($bsMonth),
            'days' => $days,
        ]);
    }

    #[Permissions('create_attendance', group: 'attendance', desc: 'Bulk Attendance')]
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
