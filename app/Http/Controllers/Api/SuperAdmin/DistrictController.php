<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\District;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\DistrictResource;
use App\Http\Requests\Api\SuperAdmin\DistrictRequest;

class DistrictController extends Controller
{
    public function index(Request $request)
    {
        $q = District::query()
            ->with('province')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('province_id')) {
            $q->where('province_id', $request->query('province_id'));
        }

        return DistrictResource::collection($q->get());
    }

    public function store(DistrictRequest $request)
    {
        $district = District::create($request->validated());
        $district->load('province');

        return response()->json([
            'data' => DistrictResource::make($district),
            'message' => 'District added successfully.',
        ], 201);
    }

    public function show(District $district)
    {
        $district->load('province');

        return DistrictResource::make($district);
    }

    public function update(DistrictRequest $request, District $district)
    {
        $district->update($request->validated());
        $district->load('province');

        return response()->json([
            'data' => DistrictResource::make($district),
            'message' => 'District updated successfully.',
        ]);
    }

    public function destroy(District $district)
    {
        if ($district->palikas()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a district that has palikas.',
            ], 422);
        }

        $district->delete();

        return response()->json([
            'message' => 'District deleted successfully.',
        ]);
    }
}
