<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use App\Http\Resources\Customer\AddressResource;
use App\Http\Resources\Vendor\OrderItemResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'order_no' => $this->order_no ?? '',
            'order_date' => $this->order_date ?? '',
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'sub_total' => $this->order_items_sum_total ?? 0,
            'discount' => 0,
            'shipping_charge' => $this->vendor_orders_sum_shipping_charge ?? 0,
            'vendor_earning' => $this->order_items_sum_total ?? 0,
            'admin_fee' => 0,
            'total_amount' => $this->totalAmount(),
            'payment_status' => $this->payment_status ?? '',
            'order_status' => $this->order_status ?? '',
            $this->mergeWhen($request->routeIs('api.admin.order.show'), function () {
                return [
                    'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
                    'billing_address' => AddressResource::make($this->billingAddress),
                    'shipping_address' => AddressResource::make($this->shippingAddress),
                ];
            }),
        ];
    }

    private function totalAmount(): float
    {
        $total = ($this->order_items_sum_total ?? 0) + ($this->vendor_orders_sum_shipping_charge ?? 0);

        return round($total, 2);
    }
}
