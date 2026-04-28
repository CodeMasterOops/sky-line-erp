<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Palika;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\PalikaResource;
use App\Http\Requests\Api\SuperAdmin\PalikaRequest;

class PalikaController extends Controller
{
    public function index(Request $request)
    {
        $q = Palika::query()
            ->with('district.province')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('district_id')) {
            $q->where('district_id', $request->query('district_id'));
        }

        return PalikaResource::collection($q->get());
    }

    public function store(PalikaRequest $request)
    {
        $palika = Palika::create($request->validated());
        $palika->load('district');

        return response()->json([
            'data' => PalikaResource::make($palika),
            'message' => 'Palika added successfully.',
        ], 201);
    }

    public function show(Palika $palika)
    {
        $palika->load('district.province');

        return PalikaResource::make($palika);
    }

    public function update(PalikaRequest $request, Palika $palika)
    {
        $palika->update($request->validated());
        $palika->load('district');

        return response()->json([
            'data' => PalikaResource::make($palika),
            'message' => 'Palika updated successfully.',
        ]);
    }

    public function destroy(Palika $palika)
    {
        if ($palika->wards()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a palika that has wards.',
            ], 422);
        }

        $palika->delete();

        return response()->json([
            'message' => 'Palika deleted successfully.',
        ]);
    }
}
