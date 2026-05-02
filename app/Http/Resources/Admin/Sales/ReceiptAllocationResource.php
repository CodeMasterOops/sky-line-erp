<?php

namespace App\Http\Resources\Admin\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'receipt_id' => $this->receipt_id ?? '',
            'invoice_id' => $this->invoice_id ?? '',
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'invoice_no' => $this->invoice->invoice_no ?? '',
                    'invoice_date' => $this->invoice->invoice_date ?? '',
                    'due_date' => $this->invoice->due_date ?? '',
                ];
            }),
            'amount' => $this->amount ?? 0,
        ];
    }
}
