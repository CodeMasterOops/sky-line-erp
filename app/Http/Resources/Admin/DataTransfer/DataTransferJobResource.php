<?php

namespace App\Http\Resources\Admin\DataTransfer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataTransferJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'direction' => $this->direction?->value,
            'entity_type' => $this->entity_type?->value,
            'status' => $this->status?->value,
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'options' => $this->options,
            'mapping' => $this->mapping,
            'stats' => $this->stats,
            'error_summary' => $this->error_summary,
            'batch_id' => $this->batch_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'has_errors_download' => (bool) $this->result_path,
            'can_download' => $this->direction?->value === 'export' && (bool) $this->result_path,
        ];
    }
}
