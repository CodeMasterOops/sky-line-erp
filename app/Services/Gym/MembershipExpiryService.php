<?php

namespace App\Services\Gym;

use App\Models\User;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Membership;
use App\Services\TenantService;
use App\Enums\MembershipStatusEnum;
use App\Models\CompanyNotificationSetting;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MembershipExpiredNotification;
use App\Notifications\MembershipExpiryReminderNotification;

/**
 * Automatic expiry and the reminders that lead up to it.
 *
 * Both sweeps run per company so they can respect that company's notification
 * settings and its own timezone, and both are safe to re-run: expiry only ever
 * moves an already-elapsed term, and each reminder offset is recorded on the
 * membership before it can be sent again.
 */
class MembershipExpiryService
{
    public const DEFAULT_REMINDER_DAYS = [7, 3, 1];

    public function __construct(private readonly MemberStatusSynchroniser $memberStatus) {}

    /**
     * Mark every term whose last day (plus the plan's grace period) has passed.
     *
     * @return int the number of memberships expired
     */
    public function expireDueMemberships(Company $company): int
    {
        $today = $this->today($company);
        $expired = 0;

        $this->scoped($company, function () use ($company, $today, &$expired): void {
            Membership::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->active()
                ->whereDate('end_date', '<=', $today->toDateString())
                ->with(['membershipPlan', 'member'])
                ->chunkById(200, function ($memberships) use ($today, &$expired): void {
                    foreach ($memberships as $membership) {
                        // Grace days are read from the plan, so a term is not
                        // expired the instant it ends if the gym allows a few
                        // days of grace.
                        if ($membership->graceEndDate()->gte($today)) {
                            continue;
                        }

                        $membership->update([
                            'status' => MembershipStatusEnum::Expired,
                            'expired_at' => now(),
                        ]);

                        if ($membership->member) {
                            $this->memberStatus->sync($membership->member);
                        }

                        $expired++;
                    }
                });
        });

        if ($expired > 0) {
            $this->notifyExpired($company);
        }

        return $expired;
    }

    /**
     * Send "your membership ends in N days" reminders.
     *
     * @return int the number of reminders dispatched
     */
    public function dispatchReminders(Company $company): int
    {
        $settings = $this->notificationSettings($company);

        if ($settings && ! $settings->membership_expiry_reminder) {
            return 0;
        }

        $offsets = $this->reminderOffsets($settings);
        $today = $this->today($company);
        $sent = 0;

        $this->scoped($company, function () use ($company, $offsets, $today, &$sent): void {
            foreach ($offsets as $offset) {
                $targetDate = $today->copy()->addDays($offset)->toDateString();

                Membership::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->active()
                    ->whereDate('end_date', $targetDate)
                    ->with(['member.party', 'membershipPlan'])
                    ->chunkById(200, function ($memberships) use ($company, $offset, &$sent): void {
                        foreach ($memberships as $membership) {
                            $alreadySent = $membership->reminders_sent ?? [];

                            if (in_array($offset, $alreadySent, true)) {
                                continue;
                            }

                            $recipients = $this->recipientsFor($company);

                            if ($recipients->isNotEmpty()) {
                                Notification::send(
                                    $recipients,
                                    new MembershipExpiryReminderNotification($membership, $offset),
                                );
                            }

                            // Recorded whether or not anyone was listening, so a
                            // company with no staff configured does not build up
                            // a backlog to send later.
                            $membership->update([
                                'reminders_sent' => [...$alreadySent, $offset],
                            ]);

                            $sent++;
                        }
                    });
            }
        });

        return $sent;
    }

    private function notifyExpired(Company $company): void
    {
        $settings = $this->notificationSettings($company);

        if ($settings && ! $settings->membership_expired_alert) {
            return;
        }

        $recipients = $this->recipientsFor($company);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new MembershipExpiredNotification($company));
    }

    /**
     * Company staff who should hear about memberships. Admins always; other
     * users when they hold the membership permission.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function recipientsFor(Company $company)
    {
        return User::query()
            ->where('company_id', $company->id)
            ->get()
            ->filter(function (User $user): bool {
                if ($user->isAdmin()) {
                    return true;
                }

                return in_array('list_membership', userPermissions($user), true);
            })
            ->values();
    }

    private function notificationSettings(Company $company): ?CompanyNotificationSetting
    {
        return CompanyNotificationSetting::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();
    }

    /**
     * @return list<int>
     */
    private function reminderOffsets(?CompanyNotificationSetting $settings): array
    {
        $days = $settings?->membership_expiry_reminder_days ?: self::DEFAULT_REMINDER_DAYS;

        return collect($days)
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * "Today" in the company's own timezone — a sweep must not expire a term a
     * day early for a gym on the other side of the date line from the server.
     */
    private function today(Company $company): \Carbon\CarbonInterface
    {
        // Company preferences live in `settings` under company.{id}.{key}
        // (see CompanyPreferencesStep); there is no per-company accessor, so
        // read it directly rather than through the global setting() helper,
        // which is documented as super-admin-only.
        $timezone = Setting::query()
            ->where('key', "company.{$company->id}.timezone")
            ->value('value');

        return now($timezone ?: config('app.timezone'))->startOfDay();
    }

    /**
     * Run a callback with the company as the active tenant, then restore
     * whatever was there before. Commands run outside a request, so nothing
     * else establishes the context.
     */
    private function scoped(Company $company, callable $callback): void
    {
        $previousCompany = TenantService::companyId();
        $previousBranch = TenantService::branchId();

        TenantService::setCompanyId($company->id);
        TenantService::setBranchId(null);

        try {
            $callback();
        } finally {
            TenantService::setCompanyId($previousCompany);
            TenantService::setBranchId($previousBranch);
        }
    }
}
