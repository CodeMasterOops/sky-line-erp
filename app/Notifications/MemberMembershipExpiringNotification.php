<?php

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The member-facing counterpart of MembershipExpiryReminderNotification.
 *
 * Off unless the company sets `notify_member_directly`, because emailing a
 * gym's customers is the gym's decision, not a default we make for them.
 */
class MemberMembershipExpiringNotification extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->membership->membershipPlan?->name;
        $endDate = $this->membership->end_date?->toDateString();

        return (new MailMessage)
            ->subject('Your membership is expiring soon')
            ->greeting('Hello '.($this->membership->member?->party?->name ?? 'there').',')
            ->line($this->line())
            ->line($plan ? 'Plan: '.$plan : '')
            ->line($endDate ? 'Valid until: '.$endDate : '')
            ->line('Speak to us at the front desk to renew.');
    }

    private function line(): string
    {
        return match (true) {
            $this->daysRemaining === 0 => 'Your gym membership ends today.',
            $this->daysRemaining === 1 => 'Your gym membership ends tomorrow.',
            default => "Your gym membership ends in {$this->daysRemaining} days.",
        };
    }
}
