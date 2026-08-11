<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\FollowUp;
use App\Enums\TaskStatusEnum;
use Illuminate\Console\Command;
use App\Enums\FollowUpStatusEnum;
use App\Notifications\CrmReminderNotification;
use App\Services\Modules\CompanyModuleService;

class DispatchCrmRemindersCommand extends Command
{
    protected $signature = 'crm:dispatch-reminders';

    protected $description = 'Notify assignees of due CRM follow-ups and tasks (queued, once each)';

    public function handle(): int
    {
        // Background work follows the module switch too: a company that has CRM
        // turned off must stop receiving CRM reminders, not just lose the menu.
        $companyIds = app(CompanyModuleService::class)->companyIdsWith('crm');

        if ($companyIds === []) {
            $this->info('No company has the CRM module enabled.');

            return Command::SUCCESS;
        }

        $followUps = $this->dispatchFollowUpReminders($companyIds);
        $tasks = $this->dispatchTaskReminders($companyIds);

        $this->info("Dispatched {$followUps} follow-up and {$tasks} task reminder(s).");

        return Command::SUCCESS;
    }

    /**
     * @param  list<int>  $companyIds
     */
    private function dispatchFollowUpReminders(array $companyIds): int
    {
        $count = 0;

        FollowUp::query()
            ->whereIn('company_id', $companyIds)
            ->whereNull('reminded_at')
            ->where('status', FollowUpStatusEnum::Pending->value)
            ->whereNotNull('user_id')
            ->where('scheduled_at', '<=', now())
            ->with(['user', 'party'])
            ->chunkById(200, function ($followUps) use (&$count) {
                foreach ($followUps as $followUp) {
                    $followUp->user?->notify(new CrmReminderNotification($followUp));
                    $followUp->update(['reminded_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  list<int>  $companyIds
     */
    private function dispatchTaskReminders(array $companyIds): int
    {
        $count = 0;

        Task::query()
            ->whereIn('company_id', $companyIds)
            ->whereNull('reminded_at')
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->whereNotIn('status', [TaskStatusEnum::Done->value, TaskStatusEnum::Cancelled->value])
            ->whereNotNull('assigned_to_user_id')
            ->with(['assignee'])
            ->chunkById(200, function ($tasks) use (&$count) {
                foreach ($tasks as $task) {
                    $task->assignee?->notify(new CrmReminderNotification($task));
                    $task->update(['reminded_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }
}
