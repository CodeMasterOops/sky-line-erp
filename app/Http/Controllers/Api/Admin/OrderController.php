<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Enums\OrderStatusEnum;
use App\Annotation\Permissions;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;
use App\Http\Resources\Admin\OrderResource;

class OrderController extends Controller
{
    /**
     * @Permissions("list_order", group="order", desc="List Order")
     */
    public function index(Request $request)
    {
        $orders = Order::with('customer')
            ->withSum('orderItems', 'total')
            ->withSum('vendorOrders', 'shipping_charge')
            ->latest()
            ->paginate($request->query('limit', 25));

        return OrderResource::collection($orders);
    }

    /**
     * @Permissions("show_order", group="order", desc="Show Order")
     */
    public function show(Order $order)
    {
        $order->load([
            'orderItems' => function ($query) {
                $query->with('productVariant.product', 'vendor:id,vendor_name');
            },
            'customer',
            'billingAddress',
            'shippingAddress',
        ])
            ->loadSum('orderItems', 'total')
            ->loadSum('vendorOrders', 'shipping_charge');

        return OrderResource::make($order);
    }

    /**
     * @Permissions("delete_order", group="order", desc="Delete Order")
     */
    public function destroy(Order $order)
    {
        $order->vendorOrders()->delete();
        $order->orderItems()->delete();
        $order->delete();

        return response()->json([
            'message' => 'Order Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_order_status", group="order", desc="Update Order Status")
     */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'order_status' => ['required', Rule::enum(OrderStatusEnum::class)],
        ]);

        $order->update([
            'order_status' => $request->order_status,
        ]);

        foreach ($order->vendorOrders as $vendorOrder) {
            $vendorOrder->update([
                'order_status' => $request->order_status,
            ]);
        }

        return response()->json([
            'message' => 'Order Status Updated Successfully',
        ]);
    }

    /**
     * @Permissions("print_shipping_label", group="order", desc="Print Shipping Label")
     */
    public function shippingLabel(Order $order)
    {
        $order->load([
            'shippingAddress',
            'vendorOrders' => function ($query) {
                $query->with('vendor', 'orderItems.productVariant.product');
            },
        ]);

        $shippingAddress = $order->shippingAddress ?? null;

        // return view('print.shipping-label', compact('order', 'shippingAddress'));

        return response()->json([
            'data' => (string) View::make('print.shipping-label', compact('order', 'shippingAddress')),
        ]);
    }
}
