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
            'payable_type' => $this->payable_type ?? '',
            'payable_id' => $this->payable_id ?? '',
            'payable' => $this->whenLoaded('payable', function () {
                if ($this->payable instanceof \App\Models\Bill) {
                    return [
                        'type' => 'bill',
                        'id' => $this->payable->id,
                        'reference_no' => $this->payable->bill_no ?? '',
                        'date' => $this->payable->bill_date ?? '',
                        'due_date' => $this->payable->due_date ?? '',
                    ];
                }
                if ($this->payable instanceof \App\Models\Expense) {
                    return [
                        'type' => 'expense',
                        'id' => $this->payable->id,
                        'reference_no' => $this->payable->reference_no ?? '',
                        'date' => $this->payable->date ?? '',
                        'due_date' => $this->payable->due_date ?? '',
                    ];
                }
                return null;
            }),
            'amount' => $this->amount ?? 0,
        ];
    }
}
