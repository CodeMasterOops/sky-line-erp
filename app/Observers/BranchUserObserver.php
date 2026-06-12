<?php

namespace App\Observers;

use App\Models\BranchUser;
use Illuminate\Support\Facades\Log;

class BranchUserObserver
{
    public function created(BranchUser $branchUser): void
    {
        Log::channel('single')->info('branch-assignment-created', [
            'branch_id' => $branchUser->branch_id,
            'user_id' => $branchUser->user_id,
            'role_id' => $branchUser->role_id,
            'performed_by' => auth('admin')->id(),
            'company_id' => $branchUser->company_id,
        ]);
    }

    public function updated(BranchUser $branchUser): void
    {
        $changed = $branchUser->getChanges();
        unset($changed['updated_at']);

        if (empty($changed)) {
            return;
        }

        Log::channel('single')->info('branch-assignment-updated', [
            'branch_id' => $branchUser->branch_id,
            'user_id' => $branchUser->user_id,
            'changes' => $changed,
            'performed_by' => auth('admin')->id(),
            'company_id' => $branchUser->company_id,
        ]);
    }

    public function deleted(BranchUser $branchUser): void
    {
        Log::channel('single')->info('branch-assignment-deleted', [
            'branch_id' => $branchUser->branch_id,
            'user_id' => $branchUser->user_id,
            'performed_by' => auth('admin')->id(),
            'company_id' => $branchUser->company_id,
        ]);
    }
}
