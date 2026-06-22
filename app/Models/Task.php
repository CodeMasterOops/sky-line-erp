<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\TaskStatusEnum;
use App\Enums\TaskPriorityEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use BranchTenant;
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;
    use MultiTenant;
    use SoftDeletes;

    protected $table = 'crm_tasks';

    protected $fillable = [
        'company_id',
        'branch_id',
        'taskable_type',
        'taskable_id',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to_user_id',
        'created_by_user_id',
        'due_date',
        'reminder_at',
        'reminded_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriorityEnum::class,
            'status' => TaskStatusEnum::class,
            'due_date' => 'date:Y-m-d',
            'reminder_at' => 'datetime',
            'reminded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $param
     */
    public function scopeFilter(Builder $query, array $param = []): Builder
    {
        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        if (! empty($param['priority'])) {
            $query->where('priority', $param['priority']);
        }

        if (! empty($param['assigned_to_user_id'])) {
            $query->where('assigned_to_user_id', $param['assigned_to_user_id']);
        }

        if (! empty($param['taskable_id']) && ! empty($param['taskable_type'])) {
            $query->where('taskable_type', $param['taskable_type'])
                ->where('taskable_id', $param['taskable_id']);
        }

        if (! empty($param['overdue'])) {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', [TaskStatusEnum::Done->value, TaskStatusEnum::Cancelled->value]);
        }

        return $query;
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
