<?php

namespace App\Services\Crm;

use App\Models\CrmActivity;
use App\Enums\CrmActivityTypeEnum;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Record a CRM-native activity against a subject (typically a Party).
     *
     * Company and branch are resolved automatically by the MultiTenant /
     * BranchTenant traits on the CrmActivity model; the causer falls back to
     * the authenticated admin user when present.
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(
        Model $subject,
        CrmActivityTypeEnum|string $type,
        string $description,
        array $properties = [],
    ): CrmActivity {
        return $subject->activities()->create([
            'causer_id' => auth('admin')->id(),
            'type' => $type instanceof CrmActivityTypeEnum ? $type->value : $type,
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'occurred_at' => now(),
        ]);
    }
}
