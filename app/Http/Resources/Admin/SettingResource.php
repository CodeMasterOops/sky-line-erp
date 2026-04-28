<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use App\Enums\InventoryCostingMethodEnum;
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
            'ward_id' => $this->ward_id,
            'postal_code' => $this->postal_code ?? '',
            'province_id' => $this->ward?->palika?->district?->province_id,
            'district_id' => $this->ward?->palika?->district_id,
            'palika_id' => $this->ward?->palika_id,
            'location_label' => $this->locationLabel(),
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'inventory_costing_method' => $this->inventory_costing_method?->value
                ?? InventoryCostingMethodEnum::FIFO->value,
            'inventory_costing_method_options' => InventoryCostingMethodEnum::optionsForSelect(),
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
