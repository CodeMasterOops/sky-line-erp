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
            'ward_id' => $this->ward_id,
            'postal_code' => $this->postal_code ?? '',
            'province_id' => $this->ward?->palika?->district?->province_id,
            'district_id' => $this->ward?->palika?->district_id,
            'palika_id' => $this->ward?->palika_id,
            'location_label' => $this->locationLabel(),
            'is_active' => $this->is_active ?? true,
        ];
    }

    private function locationLabel(): string
    {
        $ward = $this->ward;
        if (! $ward) {
            return '';
        }
        $parts = array_filter([
            $ward->palika?->district?->province?->name,
            $ward->palika?->district?->name,
            $ward->palika?->name,
            $ward->name,
        ]);

        return implode(', ', $parts);
    }
}
