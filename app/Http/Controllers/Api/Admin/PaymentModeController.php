<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\PaymentMode;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaymentModeResource;
use App\Http\Requests\Api\Admin\PaymentModeRequest;

class PaymentModeController extends Controller
{
    /**
     * @Permissions("list_payment_mode", group="payment_mode", desc="List Payment Mode")
     */
    public function index()
    {
        $paymentModes = PaymentMode::all();

        return PaymentModeResource::collection($paymentModes);
    }

    /**
     * @Permissions("create_payment_mode", group="payment_mode", desc="Create Payment Mode")
     */
    public function store(PaymentModeRequest $request)
    {
        $paymentMode = PaymentMode::create($request->validated());

        return response()->json([
            'data' => PaymentModeResource::make($paymentMode),
            'message' => 'Payment Mode Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_payment_mode", group="payment_mode", desc="Show Payment Mode")
     */
    public function show(PaymentMode $paymentMode)
    {
        return PaymentModeResource::make($paymentMode);
    }

    /**
     * @Permissions("edit_payment_mode", group="payment_mode", desc="Edit Payment Mode")
     */
    public function update(PaymentModeRequest $request, PaymentMode $paymentMode)
    {
        $paymentMode->update($request->validated());

        return response()->json([
            'data' => PaymentModeResource::make($paymentMode),
            'message' => 'Payment Mode Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_payment_mode", group="payment_mode", desc="Delete Payment Mode")
     */
    public function destroy(PaymentMode $paymentMode)
    {
        $paymentMode->delete();

        return response()->json([
            'message' => 'Payment Mode Deleted Successfully',
        ]);
    }
}
