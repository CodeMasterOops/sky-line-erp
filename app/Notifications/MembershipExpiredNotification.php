<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sent once per expiry sweep that actually expired something, so staff know to
 * chase renewals. Deliberately a summary rather than one notification per
 * member — a gym expiring fifty terms on the 1st of the month should not bury
 * the notification bell.
 */
class MembershipExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'membership_expired',
            'company_id' => $this->company->id,
            'message' => 'One or more memberships have expired. Review them to follow up on renewals.',
        ];
    }
}
