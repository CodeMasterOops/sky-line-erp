<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\WorkSchedule;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HR\WorkScheduleResource;
use App\Http\Requests\Api\Admin\HR\WorkScheduleRequest;

class WorkScheduleController extends Controller
{
    #[Permissions('show_work_schedule', group: 'work_schedule', desc: 'Show Work Schedule')]
    public function show(): WorkScheduleResource
    {
        return WorkScheduleResource::make($this->defaultSchedule());
    }

    #[Permissions('edit_work_schedule', group: 'work_schedule', desc: 'Edit Work Schedule')]
    public function update(WorkScheduleRequest $request)
    {
        $schedule = $this->defaultSchedule();
        $schedule->update($request->validated());

        return response()->json([
            'data' => WorkScheduleResource::make($schedule->fresh()),
            'message' => 'Work Schedule Updated Successfully',
        ]);
    }

    private function defaultSchedule(): WorkSchedule
    {
        return WorkSchedule::firstOrCreate(
            ['company_id' => auth('admin')->user()->company_id, 'is_default' => true],
            ['name' => 'Default'],
        );
    }
}
