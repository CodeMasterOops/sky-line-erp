<?php

namespace App\Http\Resources\Admin\Concerns;

use App\Models\Party;

trait MapsPartyFields
{
    /**
     * @return array{party_address: string, party_phone: string, party_pan: string}
     */
    protected function mapPartyFields(?Party $party): array
    {
        return [
            'party_address' => $party?->address ?? '',
            'party_phone' => $party?->phone ?? '',
            'party_pan' => $party?->pan ?? '',
        ];
    }
}
