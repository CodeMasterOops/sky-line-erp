<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'vendor_name' => $this->vendor_name ?? '',
            'slug' => $this->slug ?? '',
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'logo' => $this->logo ?? '',
            'banner_image' => $this->banner_image ?? '',
            'og_image' => $this->og_image ?? '',
            'address' => $this->address ?? '',
            'description' => $this->description ?? '',
            'facebook_link' => $this->facebook_link ?? '',
            'twitter_link' => $this->twitter_link ?? '',
            'instagram_link' => $this->instagram_link ?? '',
            'youtube_link' => $this->youtube_link ?? '',
            'google_map_link' => $this->google_map_link ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_keywords' => $this->meta_keywords ?? '',
            'meta_description' => $this->meta_description ?? '',
            'is_active' => $this->is_active ?? false,
            'vendor_status' => $this->vendor_status ?? '',
            'vendor_status_label' => $this->vendor_status?->label() ?? '',
            'user_name' => $this->admin->name ?? '',
            'user_phone' => $this->admin->phone ?? '',
            'user_email' => $this->admin->email ?? '',
        ];
    }
}
