<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'company_name' => $this->company_name ?? '',
            'legal_name' => $this->legal_name ?? '',
            'code' => $this->code ?? '',
            'pan' => $this->pan ?? '',
            'logo_url' => $this->logo_url ?? '',
            'phone' => $this->phone ?? '',
            'landline' => $this->landline ?? '',
            'email' => $this->email ?? '',
            'user_name' => $this->admin->name ?? '',
            'user_phone' => $this->admin->phone ?? '',
            'user_email' => $this->admin->email ?? '',
            'website' => $this->website ?? '',
            'address' => $this->address ?? '',
            'status' => $this->status ?? true,
        ];
    }
}
