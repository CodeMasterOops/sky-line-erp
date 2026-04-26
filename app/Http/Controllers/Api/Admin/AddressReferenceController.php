<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Ward;
use App\Models\Palika;
use App\Models\District;
use App\Models\Province;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\WardResource;
use App\Http\Resources\SuperAdmin\PalikaResource;
use App\Http\Resources\SuperAdmin\DistrictResource;
use App\Http\Resources\SuperAdmin\ProvinceResource;

class AddressReferenceController extends Controller
{
    public function provinces()
    {
        $provinces = Province::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ProvinceResource::collection($provinces);
    }

    public function districts(Request $request)
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

    public function palikas(Request $request)
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

    public function wards(Request $request)
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
}
