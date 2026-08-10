<?php

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "X's membership ends in N days" — sent to gym staff at each configured
 * offset before the term's last day.
 */
class MembershipExpiryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Membership $membership,
        public int $daysRemaining,
    ) {}

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
            'type' => 'membership_expiry_reminder',
            'membership_id' => $this->membership->id,
            'membership_no' => $this->membership->membership_no,
            'member_id' => $this->membership->member_id,
            'member_name' => $this->membership->member?->party?->name,
            'plan_name' => $this->membership->membershipPlan?->name,
            'end_date' => $this->membership->end_date?->toDateString(),
            'days_remaining' => $this->daysRemaining,
            'message' => $this->message(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Membership expiring soon')
            ->line($this->message())
            ->line('Plan: '.($this->membership->membershipPlan?->name ?? '—'))
            ->line('Ends on: '.($this->membership->end_date?->toDateString() ?? '—'));
    }

    private function message(): string
    {
        $name = $this->membership->member?->party?->name ?? 'A member';

        return match (true) {
            $this->daysRemaining === 0 => sprintf("%s's membership ends today.", $name),
            $this->daysRemaining === 1 => sprintf("%s's membership ends tomorrow.", $name),
            default => sprintf("%s's membership ends in %d days.", $name, $this->daysRemaining),
        };
    }
}
