<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use App\Services\Gym\MembershipExpiryService;
use App\Services\Modules\CompanyModuleService;

class ProcessMembershipExpiryCommand extends Command
{
    protected $signature = 'gym:process-membership-expiry';

    protected $description = 'Expire membership terms whose last day (plus grace) has passed';

    public function handle(CompanyModuleService $modules, MembershipExpiryService $expiry): int
    {
        $companyIds = $modules->companyIdsWith('gym');

        if ($companyIds === []) {
            $this->info('No company has the Gym module enabled.');

            return self::SUCCESS;
        }

        $total = 0;

        Company::query()->whereIn('id', $companyIds)->each(function (Company $company) use ($expiry, &$total): void {
            $expired = $expiry->expireDueMemberships($company);
            $total += $expired;

            if ($expired > 0) {
                $this->line("{$company->company_name}: expired {$expired} membership(s).");
            }
        });

        $this->info("Expired {$total} membership(s).");

        return self::SUCCESS;
    }
}
