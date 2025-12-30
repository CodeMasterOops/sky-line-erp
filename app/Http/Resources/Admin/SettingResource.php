<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this->company_name ?? '',
            'legal_name' => $this->legal_name ?? '',
            'code' => $this->code ?? '',
            'pan' => $this->pan ?? '',
            'logo_url' => $this->logo_url ?? '',
            'phone' => $this->phone ?? '',
            'landline' => $this->landline ?? '',
            'email' => $this->email ?? '',
            'website' => $this->website ?? '',
            'address' => $this->address ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
        ];
    }
}
