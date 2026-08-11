<?php

namespace App\Services\Gym;

use App\Models\Party;
use App\Models\Member;
use App\Enums\PartyTypeEnum;
use App\Enums\EntityCodeType;
use App\Enums\MemberStatusEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use App\Services\EntityCodeGenerator;
use App\Services\Party\PartyCodeGenerator;
use Illuminate\Validation\ValidationException;

/**
 * Creating and updating members.
 *
 * A member is two rows written as one: the `party` that carries identity and
 * everything financial, and the `member` that carries the gym profile. Callers
 * see a single entity; the split never leaks into the API.
 */
class MemberService
{
    public function __construct(
        private readonly EntityCodeGenerator $codeGenerator,
        private readonly PartyCodeGenerator $partyCodeGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): Member
    {
        return DB::transaction(function () use ($data, $userId): Member {
            $companyId = $this->companyId();

            $party = Party::create([
                'type' => PartyTypeEnum::CUSTOMER,
                'name' => $data['name'],
                'code' => $this->partyCodeGenerator->generate(PartyTypeEnum::CUSTOMER, $companyId),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'pan' => $data['pan'] ?? null,
                'is_active' => true,
            ]);

            return Member::create([
                'party_id' => $party->id,
                'member_code' => $data['member_code'] ?? $this->nextCode($companyId),
                'photo' => $data['photo'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'weight_kg' => $data['weight_kg'] ?? null,
                'medical_notes' => $data['medical_notes'] ?? null,
                'joined_on' => $data['joined_on'] ?? now()->toDateString(),
                // A member starts inactive and becomes active when a membership
                // is assigned (Phase 6). Nobody is "active" without a term.
                'status' => $data['status'] ?? MemberStatusEnum::Inactive,
                'source' => $data['source'] ?? null,
                'referred_by_member_id' => $data['referred_by_member_id'] ?? null,
                'assigned_trainer_id' => $data['assigned_trainer_id'] ?? null,
                'created_by_id' => $userId,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Member $member, array $data): Member
    {
        return DB::transaction(function () use ($member, $data): Member {
            $partyFields = array_filter(
                [
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'pan' => $data['pan'] ?? null,
                ],
                fn ($value): bool => $value !== null,
            );

            if ($partyFields !== []) {
                $member->party->update($partyFields);
            }

            $member->update(array_intersect_key($data, array_flip([
                'photo',
                'date_of_birth',
                'gender',
                'blood_group',
                'occupation',
                'emergency_contact_name',
                'emergency_contact_phone',
                'height_cm',
                'weight_kg',
                'medical_notes',
                'joined_on',
                'source',
                'referred_by_member_id',
                'assigned_trainer_id',
            ])));

            return $member->fresh(['party']);
        });
    }

    /**
     * Soft-delete a member and the party behind it.
     *
     * Refused while the member has any membership history, so the financial
     * trail behind those terms is never orphaned. (Memberships arrive in
     * Phase 6; the guard is written against the table so it holds the moment
     * they do.)
     */
    public function delete(Member $member): void
    {
        if ($this->hasMembershipHistory($member)) {
            throw ValidationException::withMessages([
                'member' => ['This member has membership history and cannot be deleted. Mark them inactive instead.'],
            ]);
        }

        DB::transaction(function () use ($member): void {
            $party = $member->party;
            $member->delete();
            $party?->delete();
        });
    }

    /**
     * The next member ID for a company, e.g. MEM-00001.
     *
     * Generated inside the generator's company-level FOR UPDATE lock, so two
     * front-desk registrations at once cannot collide.
     */
    public function nextCode(?int $companyId = null): string
    {
        $companyId ??= $this->companyId();

        if (DB::transactionLevel() > 0) {
            return $this->codeGenerator->generateForType(EntityCodeType::Member, $companyId);
        }

        return DB::transaction(fn (): string => $this->codeGenerator->generateForType(EntityCodeType::Member, $companyId));
    }

    private function hasMembershipHistory(Member $member): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('memberships')) {
            return false;
        }

        return DB::table('memberships')->where('member_id', $member->id)->exists();
    }

    private function companyId(): int
    {
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company' => ['Company context is not available.'],
            ]);
        }

        return (int) $companyId;
    }
}
