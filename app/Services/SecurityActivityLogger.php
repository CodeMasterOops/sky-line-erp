<?php

namespace App\Services;

use App\Models\User;
use App\Models\SecurityActivity;

class SecurityActivityLogger
{
    /**
     * Record a security event for the given user, capturing the current
     * request's IP address and user agent.
     */
    public function log(User $user, string $event, ?string $description = null): SecurityActivity
    {
        return SecurityActivity::create([
            'company_id' => $user->company_id,
            'user_id' => $user->getKey(),
            'event' => $event,
            'description' => $description,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
        ]);
    }
}
