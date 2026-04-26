<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Ward;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\WardResource;
use App\Http\Requests\Api\SuperAdmin\WardRequest;

class WardController extends Controller
{
    public function index(Request $request)
    {
        $q = Ward::query()
            ->with('palika.district.province')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('palika_id')) {
            $q->where('palika_id', $request->query('palika_id'));
        }

        return WardResource::collection($q->get());
    }

    public function store(WardRequest $request)
    {
        $ward = Ward::create($request->validated());
        $ward->load('palika');

        return response()->json([
            'data' => WardResource::make($ward),
            'message' => 'Ward added successfully.',
        ], 201);
    }

    public function show(Ward $ward)
    {
        $ward->load('palika.district.province');

        return WardResource::make($ward);
    }

    public function update(WardRequest $request, Ward $ward)
    {
        $ward->update($request->validated());
        $ward->load('palika');

        return response()->json([
            'data' => WardResource::make($ward),
            'message' => 'Ward updated successfully.',
        ]);
    }

    public function destroy(Ward $ward)
    {
        if (Company::query()->where('ward_id', $ward->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a ward that is assigned to a company.',
            ], 422);
        }

        $ward->delete();

        return response()->json([
            'message' => 'Ward deleted successfully.',
        ]);
    }
}
