<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use App\Services\Gym\MembershipExpiryService;
use App\Services\Modules\CompanyModuleService;

class DispatchMembershipRemindersCommand extends Command
{
    protected $signature = 'gym:dispatch-membership-reminders';

    protected $description = 'Notify staff of memberships expiring at each configured day offset';

    public function handle(CompanyModuleService $modules, MembershipExpiryService $expiry): int
    {
        $companyIds = $modules->companyIdsWith('gym');

        if ($companyIds === []) {
            $this->info('No company has the Gym module enabled.');

            return self::SUCCESS;
        }

        $total = 0;

        Company::query()->whereIn('id', $companyIds)->each(function (Company $company) use ($expiry, &$total): void {
            $total += $expiry->dispatchReminders($company);
        });

        $this->info("Dispatched {$total} membership reminder(s).");

        return self::SUCCESS;
    }
}
