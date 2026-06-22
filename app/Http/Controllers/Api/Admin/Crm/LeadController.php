<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use App\Models\Party;
use App\Enums\PartyTypeEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Services\TenantService;
use App\Enums\CrmLeadStatusEnum;
use App\Enums\CrmActivityTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Services\Crm\LeadConverter;
use App\Http\Controllers\Controller;
use App\Services\Crm\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Party\PartyCodeGenerator;
use App\Http\Resources\Admin\Crm\LeadResource;
use App\Http\Requests\Api\Admin\Crm\LeadRequest;
use App\Http\Requests\Api\Admin\Crm\AssignLeadRequest;
use App\Http\Requests\Api\Admin\Crm\UpdateLeadStatusRequest;

class LeadController extends Controller
{
    public function __construct(
        private PartyCodeGenerator $partyCodeGenerator,
        private ActivityLogger $activityLogger,
        private LeadConverter $leadConverter,
    ) {}

    #[Permissions('list_crm_lead', group: 'crm_lead', desc: 'List Leads')]
    public function index(Request $request)
    {
        $leads = Party::query()
            ->where('type', PartyTypeEnum::LEAD)
            ->with(['leadProfile.assignedUser'])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $key = '%'.trim($request->string('search')).'%';
                $query->where(function (Builder $q) use ($key) {
                    $q->where('name', 'like', $key)
                        ->orWhere('code', 'like', $key)
                        ->orWhere('phone', 'like', $key)
                        ->orWhere('email', 'like', $key);
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request) {
                $query->whereHas('leadProfile', function (Builder $q) use ($request) {
                    $q->where('status', $request->string('status'));
                });
            })
            ->latest()
            ->paginate($request->limit ?? 25);

        return LeadResource::collection($leads);
    }

    #[Permissions('create_crm_lead', group: 'crm_lead', desc: 'Create Lead')]
    public function nextCode()
    {
        return response()->json([
            'data' => [
                'code' => $this->partyCodeGenerator->generate(PartyTypeEnum::LEAD, $this->resolveCompanyId()),
            ],
        ]);
    }

    #[Permissions('create_crm_lead', group: 'crm_lead', desc: 'Create Lead')]
    public function store(LeadRequest $request)
    {
        $data = $request->validated();

        $lead = DB::transaction(function () use ($data) {
            $party = Party::create([
                'type' => PartyTypeEnum::LEAD,
                'name' => $data['name'],
                'code' => $data['code'] ?? $this->partyCodeGenerator->generate(PartyTypeEnum::LEAD, $this->resolveCompanyId()),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'pan' => $data['pan'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            // PartyObserver provisions the lead profile + "lead created" activity;
            // here we only layer on the optional pipeline fields supplied.
            $party->leadProfile()->update(array_filter([
                'status' => $data['status'] ?? null,
                'source' => $data['source'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'expected_value' => $data['expected_value'] ?? null,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            ], fn ($value) => $value !== null));

            return $party;
        });

        $lead->load(['leadProfile.assignedUser', 'contactPersons']);

        return response()->json([
            'data' => LeadResource::make($lead),
            'message' => 'Lead Added Successfully',
        ], 201);
    }

    #[Permissions('show_crm_lead', group: 'crm_lead', desc: 'Show Lead')]
    public function show(Party $party)
    {
        $party->load(['leadProfile.assignedUser', 'contactPersons']);

        return LeadResource::make($party);
    }

    #[Permissions('edit_crm_lead', group: 'crm_lead', desc: 'Edit Lead')]
    public function update(LeadRequest $request, Party $party)
    {
        $data = $request->validated();

        $party->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'pan' => $data['pan'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $party->leadProfile()->update(array_filter([
            'source' => $data['source'] ?? null,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'expected_value' => $data['expected_value'] ?? null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
        ], fn ($value) => $value !== null));

        $party->load(['leadProfile.assignedUser', 'contactPersons']);

        return response()->json([
            'data' => LeadResource::make($party),
            'message' => 'Lead Updated Successfully',
        ]);
    }

    #[Permissions('delete_crm_lead', group: 'crm_lead', desc: 'Delete Lead')]
    public function destroy(Party $party)
    {
        $party->delete();

        return response()->json([
            'message' => 'Lead Deleted Successfully',
        ]);
    }

    #[Permissions('assign_crm_lead', group: 'crm_lead', desc: 'Assign Lead')]
    public function assign(AssignLeadRequest $request, Party $party)
    {
        $userId = (int) $request->validated()['assigned_to_user_id'];

        $party->leadProfile()->update(['assigned_to_user_id' => $userId]);
        $this->activityLogger->log($party, CrmActivityTypeEnum::Assigned, 'Lead assigned', ['user_id' => $userId]);

        $party->load(['leadProfile.assignedUser', 'contactPersons']);

        return response()->json([
            'data' => LeadResource::make($party),
            'message' => 'Lead Assigned Successfully',
        ]);
    }

    #[Permissions('edit_crm_lead', group: 'crm_lead', desc: 'Update Lead Status')]
    public function updateStatus(UpdateLeadStatusRequest $request, Party $party)
    {
        $status = CrmLeadStatusEnum::from($request->validated()['status']);

        if ($status === CrmLeadStatusEnum::Converted) {
            return response()->json([
                'message' => 'Use the convert endpoint to convert a lead to a customer.',
            ], 422);
        }

        $profile = $party->leadProfile;
        $previous = $profile->status;

        $attributes = ['status' => $status];

        if ($status === CrmLeadStatusEnum::Qualified && $profile->qualified_at === null) {
            $attributes['qualified_at'] = now();
        }

        if ($status === CrmLeadStatusEnum::Lost) {
            $attributes['lost_reason'] = $request->validated()['lost_reason'] ?? null;
        }

        $profile->update($attributes);

        $this->activityLogger->log($party, CrmActivityTypeEnum::StatusChanged, 'Lead status changed', [
            'from' => $previous?->value,
            'to' => $status->value,
        ]);

        $party->load(['leadProfile.assignedUser', 'contactPersons']);

        return response()->json([
            'data' => LeadResource::make($party),
            'message' => 'Lead Status Updated Successfully',
        ]);
    }

    #[Permissions('convert_crm_lead', group: 'crm_lead', desc: 'Convert Lead')]
    public function convert(Party $party)
    {
        $customer = $this->leadConverter->convert($party);

        return response()->json([
            'data' => LeadResource::make($customer),
            'message' => 'Lead Converted Successfully',
        ]);
    }

    private function resolveCompanyId(): int
    {
        return TenantService::companyId()
            ?? (int) auth('admin')->user()->company_id;
    }
}
