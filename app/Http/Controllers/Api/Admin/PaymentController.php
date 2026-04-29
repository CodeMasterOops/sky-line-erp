<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Payment;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Accounting\PaymentService;
use App\Http\Resources\Admin\PaymentResource;
use App\Http\Requests\Api\Admin\PaymentRequest;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * @Permissions("list_payment", group="payment", desc="List Payment")
     */
    public function index(Request $request)
    {
        $payments = Payment::filter($request->all())
            ->with(['party', 'account', 'paymentMode', 'allocations'])
            ->latest('payment_date')
            ->paginate($request->limit ?? 25);

        return PaymentResource::collection($payments);
    }

    /**
     * @Permissions("create_payment", group="payment", desc="Create Payment")
     */
    public function store(PaymentRequest $request)
    {
        $payment = $this->paymentService->createPayment($request->validated());

        $payment->load(['party', 'account', 'paymentMode', 'allocations.payable']);

        return response()->json([
            'data' => PaymentResource::make($payment),
            'message' => 'Payment Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_payment", group="payment", desc="Show Payment")
     */
    public function show(Payment $payment)
    {
        $payment->load(['party', 'account', 'paymentMode', 'allocations.payable']);

        return PaymentResource::make($payment);
    }

    /**
     * @Permissions("edit_payment", group="payment", desc="Edit Payment")
     */
    public function update(PaymentRequest $request, Payment $payment)
    {
        if ($payment->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved payments cannot be edited.',
            ], 422);
        }

        $this->paymentService->updatePayment($request->validated(), $payment);

        $payment->load(['party', 'account', 'paymentMode', 'allocations.payable']);

        return response()->json([
            'data' => PaymentResource::make($payment),
            'message' => 'Payment Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_payment", group="payment", desc="Delete Payment")
     */
    public function destroy(Payment $payment)
    {
        $payment->allocations()->delete();
        $payment->delete();

        return response()->json([
            'message' => 'Payment Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_payment", group="payment", desc="Approve Payment")
     */
    public function approve(Payment $payment)
    {
        if ($payment->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => PaymentResource::make($payment),
                'message' => 'Payment Already Approved',
            ]);
        }

        $this->paymentService->approvePayment($payment);

        $payment->load(['party', 'account', 'paymentMode', 'allocations.payable']);

        return response()->json([
            'data' => PaymentResource::make($payment),
            'message' => 'Payment Approved Successfully',
        ]);
    }
}
