<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\SubscriptionService;

class BackfillSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $count = app(SubscriptionService::class)->backfillDefaultPlans();

        if ($this->command) {
            $this->command->info("Backfilled default subscriptions for {$count} companies.");
        }
    }
}
