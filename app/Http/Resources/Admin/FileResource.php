<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'folder_id' => $this->folder_id ?? '',
            'file_name' => $this->file_name ?? '',
            'file_type' => $this->file_type ?? '',
            'file_size' => $this->file_size ?? '',
            'extension' => $this->extension ?? '',
            'path' => $this->path ?? '',
            'file_url' => $this->file_url ?? '',
        ];
    }
}
