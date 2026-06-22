<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use App\Models\Task;
use App\Models\Party;
use Illuminate\Http\Request;
use App\Enums\TaskStatusEnum;
use App\Annotation\Permissions;
use App\Enums\TaskPriorityEnum;
use App\Enums\CrmActivityTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Crm\ActivityLogger;
use App\Http\Resources\Admin\Crm\TaskResource;
use App\Services\Crm\CustomerProfileAggregator;
use App\Http\Requests\Api\Admin\Crm\TaskRequest;

class TaskController extends Controller
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private CustomerProfileAggregator $aggregator,
    ) {}

    #[Permissions('list_crm_task', group: 'crm_task', desc: 'List Tasks')]
    public function index(Request $request)
    {
        $tasks = Task::filter($request->all())
            ->with(['assignee', 'taskable'])
            ->latest()
            ->paginate($request->limit ?? 25);

        return TaskResource::collection($tasks);
    }

    #[Permissions('list_crm_task', group: 'crm_task', desc: 'List Tasks')]
    public function mine(Request $request)
    {
        $tasks = Task::query()
            ->where('assigned_to_user_id', auth('admin')->id())
            ->whereNotIn('status', [TaskStatusEnum::Done, TaskStatusEnum::Cancelled])
            ->with(['assignee', 'taskable'])
            ->orderByRaw('due_date is null, due_date asc')
            ->paginate($request->limit ?? 25);

        return TaskResource::collection($tasks);
    }

    #[Permissions('create_crm_task', group: 'crm_task', desc: 'Create Task')]
    public function store(TaskRequest $request)
    {
        $data = array_merge($request->validated(), $request->taskableAttributes());
        $data['created_by_user_id'] = auth('admin')->id();
        $data['status'] ??= TaskStatusEnum::Open->value;
        $data['priority'] ??= TaskPriorityEnum::Medium->value;
        unset($data['party_id']);

        $task = Task::create($data);

        if ($task->taskable_type === Party::class && $task->taskable) {
            $this->activityLogger->log(
                $task->taskable,
                CrmActivityTypeEnum::TaskCreated,
                'Task created',
                ['title' => $task->title],
            );
            $this->aggregator->forget($task->taskable_id);
        }

        $task->load(['assignee', 'taskable']);

        return response()->json([
            'data' => TaskResource::make($task),
            'message' => 'Task Added Successfully',
        ], 201);
    }

    #[Permissions('edit_crm_task', group: 'crm_task', desc: 'Edit Task')]
    public function update(TaskRequest $request, Task $task)
    {
        $data = array_merge($request->validated(), $request->taskableAttributes());
        unset($data['party_id']);

        $task->update($data);
        $task->load(['assignee', 'taskable']);

        return response()->json([
            'data' => TaskResource::make($task),
            'message' => 'Task Updated Successfully',
        ]);
    }

    #[Permissions('delete_crm_task', group: 'crm_task', desc: 'Delete Task')]
    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json([
            'message' => 'Task Deleted Successfully',
        ]);
    }

    #[Permissions('edit_crm_task', group: 'crm_task', desc: 'Complete Task')]
    public function complete(Task $task)
    {
        $task->update([
            'status' => TaskStatusEnum::Done,
            'completed_at' => now(),
        ]);

        if ($task->taskable_type === Party::class && $task->taskable) {
            $this->activityLogger->log(
                $task->taskable,
                CrmActivityTypeEnum::TaskCompleted,
                'Task completed',
                ['title' => $task->title],
            );
            $this->aggregator->forget($task->taskable_id);
        }

        $task->load(['assignee', 'taskable']);

        return response()->json([
            'data' => TaskResource::make($task),
            'message' => 'Task Completed Successfully',
        ]);
    }
}
