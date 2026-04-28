<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Province;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\ProvinceResource;
use App\Http\Requests\Api\SuperAdmin\ProvinceRequest;

class ProvinceController extends Controller
{
    public function index()
    {
        $provinces = Province::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ProvinceResource::collection($provinces);
    }

    public function store(ProvinceRequest $request)
    {
        $province = Province::create($request->validated());

        return response()->json([
            'data' => ProvinceResource::make($province),
            'message' => 'Province added successfully.',
        ], 201);
    }

    public function show(Province $province)
    {
        return ProvinceResource::make($province);
    }

    public function update(ProvinceRequest $request, Province $province)
    {
        $province->update($request->validated());

        return response()->json([
            'data' => ProvinceResource::make($province),
            'message' => 'Province updated successfully.',
        ]);
    }

    public function destroy(Province $province)
    {
        if ($province->districts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a province that has districts.',
            ], 422);
        }

        $province->delete();

        return response()->json([
            'message' => 'Province deleted successfully.',
        ]);
    }
}
