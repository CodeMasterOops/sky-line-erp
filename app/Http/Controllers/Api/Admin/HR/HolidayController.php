<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\Holiday;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HolidayResource;
use App\Http\Requests\Api\Admin\HolidayRequest;

class HolidayController extends Controller
{
    /**
     * @Permissions("list_holiday", group="hr", desc="List Holiday")
     */
    public function index(Request $request)
    {
        $holidays = Holiday::filter($request->all())
            ->orderBy('date')
            ->paginate($request->limit ?? 50);

        return HolidayResource::collection($holidays);
    }

    /**
     * @Permissions("create_holiday", group="hr", desc="Create Holiday")
     */
    public function store(HolidayRequest $request)
    {
        $holiday = Holiday::create($request->validated());

        return response()->json([
            'data' => HolidayResource::make($holiday),
            'message' => 'Holiday Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_holiday", group="hr", desc="Show Holiday")
     */
    public function show(Holiday $holiday)
    {
        return HolidayResource::make($holiday);
    }

    /**
     * @Permissions("edit_holiday", group="hr", desc="Edit Holiday")
     */
    public function update(HolidayRequest $request, Holiday $holiday)
    {
        $holiday->update($request->validated());

        return response()->json([
            'data' => HolidayResource::make($holiday),
            'message' => 'Holiday Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_holiday", group="hr", desc="Delete Holiday")
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json([
            'message' => 'Holiday Deleted Successfully',
        ]);
    }
}
