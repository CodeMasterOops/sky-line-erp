<?php

namespace App\Http\Resources\Admin\DataTransfer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataTransferRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'row_number' => $this->row_number,
            'status' => $this->status?->value,
            'raw_payload' => $this->raw_payload,
            'errors' => $this->errors,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
        ];
    }
}
