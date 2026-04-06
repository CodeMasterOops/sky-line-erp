<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'payment_id' => $this->payment_id ?? '',
            'bill_id' => $this->bill_id ?? '',
            'bill' => $this->whenLoaded('bill', function () {
                return [
                    'id' => $this->bill->id,
                    'bill_no' => $this->bill->bill_no ?? '',
                    'bill_date' => $this->bill->bill_date ?? '',
                    'due_date' => $this->bill->due_date ?? '',
                ];
            }),
            'amount' => $this->amount ?? 0,
        ];
    }
}
