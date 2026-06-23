<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Party;
use App\Enums\PartyTypeEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Services\TenantService;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Services\Party\PartyCodeGenerator;
use App\Http\Resources\Admin\PartyResource;
use App\Http\Requests\Api\Admin\PartyRequest;

class PartyController extends Controller
{
    public function __construct(
        private PartyCodeGenerator $partyCodeGenerator,
    ) {}

    #[Permissions('list_party', group: 'party', desc: 'List Party')]
    public function index(Request $request)
    {
        $parties = Party::filter($request->all())
            ->with(['discount', 'leadProfile'])
            ->latest()
            ->paginate($request->limit ?? 25);

        return PartyResource::collection($parties);
    }

    #[Permissions('create_party', group: 'party', desc: 'Create Party')]
    public function nextCode(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(PartyTypeEnum::class)],
        ]);

        $type = PartyTypeEnum::from($validated['type']);
        $companyId = $this->resolveCompanyId();

        return response()->json([
            'data' => [
                'code' => $this->partyCodeGenerator->generate($type, $companyId),
            ],
        ]);
    }

    #[Permissions('create_party', group: 'party', desc: 'Create Party')]
    public function store(PartyRequest $request)
    {
        $data = $request->validated();
        $companyId = $this->resolveCompanyId();

        if (empty($data['code'])) {
            $type = PartyTypeEnum::from($data['type']);
            $data['code'] = $this->partyCodeGenerator->generate($type, $companyId);
        }

        $party = Party::create($data);
        $this->syncPartyDiscount($party, $request);

        $party->load('discount');

        return response()->json([
            'data' => PartyResource::make($party),
            'message' => 'Party Added Successfully',
        ], 201);
    }

    #[Permissions('show_party', group: 'party', desc: 'Show Party')]
    public function show(Party $party)
    {
        $party->load('discount');

        return PartyResource::make($party);
    }

    #[Permissions('edit_party', group: 'party', desc: 'Edit Party')]
    public function update(PartyRequest $request, Party $party)
    {
        $party->update($request->validated());
        $this->syncPartyDiscount($party, $request);

        $party->load('discount');

        return response()->json([
            'data' => PartyResource::make($party),
            'message' => 'Party Updated Successfully',
        ]);
    }

    #[Permissions('delete_party', group: 'party', desc: 'Delete Party')]
    public function destroy(Party $party)
    {
        $party->delete();

        return response()->json([
            'message' => 'Party Deleted Successfully',
        ]);
    }

    private function syncPartyDiscount(Party $party, PartyRequest $request): void
    {
        if (! $request->has('discount_type') && ! $request->has('discount_value')) {
            return;
        }

        $value = $request->input('discount_value');
        $hasDiscount = $value !== null && $value !== '' && (float) $value > 0;

        if (! $hasDiscount) {
            $party->discount()?->delete();

            return;
        }

        $type = $request->input('discount_type', 'fixed');
        $party->saveDiscount($type, (float) $value);
    }

    private function resolveCompanyId(): int
    {
        return TenantService::companyId()
            ?? (int) auth('admin')->user()->company_id;
    }
}
