<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Coupon;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CouponResource;
use App\Http\Requests\Api\Admin\CouponRequest;

class CouponController extends Controller
{
    /**
     * @Permissions("list_coupon", group="coupon", desc="List Coupon")
     */
    public function index(Request $request)
    {
        $coupons = Coupon::filter($request->all())
            ->paginate($request->query('limit', 25));

        return CouponResource::collection($coupons);
    }

    /**
     * @Permissions("create_coupon", group="coupon", desc="Create Coupon")
     */
    public function store(CouponRequest $request)
    {
        $coupon = DB::transaction(function () use ($request) {
            $coupon = Coupon::create($request->validated());

            $coupon->products()->attach($request->validated('products'));

            return $coupon;
        });

        return response()->json([
            'data' => CouponResource::make($coupon),
            'message' => 'Coupon Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_coupon", group="coupon", desc="Show Coupon")
     */
    public function show(Coupon $coupon)
    {
        $coupon->load('products:id,name');

        return CouponResource::make($coupon);
    }

    /**
     * @Permissions("edit_coupon", group="coupon", desc="Edit Coupon")
     */
    public function update(CouponRequest $request, Coupon $coupon)
    {
        DB::transaction(function () use ($request, $coupon) {
            $coupon->update($request->validated());

            $coupon->products()->sync($request->validated('products'));
        });

        return response()->json([
            'data' => CouponResource::make($coupon),
            'message' => 'Coupon Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_coupon", group="coupon", desc="Delete Coupon")
     */
    public function destroy(Coupon $coupon)
    {
        $coupon->products()->detach();
        $coupon->delete();

        return response()->json([
            'message' => 'Coupon Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_coupon_status", group="coupon", desc="Update Status")
     */
    public function updateStatus(Coupon $coupon)
    {
        $coupon->update([
            'is_active' => ! $coupon->is_active,
        ]);

        return response([
            'is_active' => $coupon->is_active,
            'message' => 'Status updated successfully',
        ]);
    }
}
