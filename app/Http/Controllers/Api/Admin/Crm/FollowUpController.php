<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use App\Models\FollowUp;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Enums\FollowUpStatusEnum;
use App\Enums\CrmActivityTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Crm\ActivityLogger;
use App\Http\Resources\Admin\Crm\FollowUpResource;
use App\Http\Requests\Api\Admin\Crm\FollowUpRequest;
use App\Http\Requests\Api\Admin\Crm\CompleteFollowUpRequest;

class FollowUpController extends Controller
{
    public function __construct(
        private ActivityLogger $activityLogger,
    ) {}

    #[Permissions('list_crm_follow_up', group: 'crm_follow_up', desc: 'List Follow-ups')]
    public function index(Request $request)
    {
        $followUps = FollowUp::filter($request->all())
            ->with(['party', 'user'])
            ->orderBy('scheduled_at')
            ->paginate($request->limit ?? 25);

        return FollowUpResource::collection($followUps);
    }

    #[Permissions('list_crm_follow_up', group: 'crm_follow_up', desc: 'List Follow-ups')]
    public function due(Request $request)
    {
        $followUps = FollowUp::query()
            ->where('status', FollowUpStatusEnum::Pending)
            ->where('scheduled_at', '<=', now())
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->with(['party', 'user'])
            ->orderBy('scheduled_at')
            ->paginate($request->limit ?? 25);

        return FollowUpResource::collection($followUps);
    }

    #[Permissions('create_crm_follow_up', group: 'crm_follow_up', desc: 'Create Follow-up')]
    public function store(FollowUpRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] ??= auth('admin')->id();
        $data['status'] ??= FollowUpStatusEnum::Pending->value;

        $followUp = FollowUp::create($data);

        $this->activityLogger->log(
            $followUp->party,
            CrmActivityTypeEnum::FollowUpScheduled,
            'Follow-up scheduled',
            ['channel' => $followUp->channel?->value, 'scheduled_at' => $followUp->scheduled_at?->toIso8601String()],
        );

        $followUp->load(['party', 'user']);

        return response()->json([
            'data' => FollowUpResource::make($followUp),
            'message' => 'Follow-up Scheduled Successfully',
        ], 201);
    }

    #[Permissions('edit_crm_follow_up', group: 'crm_follow_up', desc: 'Edit Follow-up')]
    public function update(FollowUpRequest $request, FollowUp $followUp)
    {
        $followUp->update($request->validated());
        $followUp->load(['party', 'user']);

        return response()->json([
            'data' => FollowUpResource::make($followUp),
            'message' => 'Follow-up Updated Successfully',
        ]);
    }

    #[Permissions('delete_crm_follow_up', group: 'crm_follow_up', desc: 'Delete Follow-up')]
    public function destroy(FollowUp $followUp)
    {
        $followUp->delete();

        return response()->json([
            'message' => 'Follow-up Deleted Successfully',
        ]);
    }

    #[Permissions('edit_crm_follow_up', group: 'crm_follow_up', desc: 'Complete Follow-up')]
    public function complete(CompleteFollowUpRequest $request, FollowUp $followUp)
    {
        $followUp->update([
            'status' => FollowUpStatusEnum::Done,
            'completed_at' => now(),
            'outcome' => $request->validated()['outcome'] ?? $followUp->outcome,
            'note' => $request->validated()['note'] ?? $followUp->note,
        ]);

        $this->activityLogger->log(
            $followUp->party,
            CrmActivityTypeEnum::FollowUpCompleted,
            'Follow-up completed',
            ['outcome' => $followUp->outcome],
        );

        $followUp->load(['party', 'user']);

        return response()->json([
            'data' => FollowUpResource::make($followUp),
            'message' => 'Follow-up Completed Successfully',
        ]);
    }
}
