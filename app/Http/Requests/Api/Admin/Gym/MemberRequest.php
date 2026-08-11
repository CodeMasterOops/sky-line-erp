<?php

namespace App\Http\Requests\Api\Admin\Gym;

use App\Tenancy\TRule;
use App\Enums\GenderEnum;
use App\Enums\BloodGroupEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One payload for the member and the party behind them — the split is an
 * implementation detail and never surfaces in the API.
 */
class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $partyId = $isUpdate ? $this->route('member')?->party_id : null;
        $memberId = $isUpdate ? $this->route('member')?->id : null;

        return [
            // Party fields
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', TRule::unique('parties')->ignore($partyId)],
            'email' => ['nullable', 'email', 'max:255', TRule::unique('parties')->ignore($partyId)],
            'address' => ['nullable', 'string', 'max:500'],

            // Member profile
            'member_code' => ['nullable', 'string', 'max:50', TRule::unique('members', 'member_code')->ignore($memberId)],
            'photo' => ['nullable', 'image', 'max:4096'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(GenderEnum::class)],
            'blood_group' => ['nullable', Rule::enum(BloodGroupEnum::class)],
            'occupation' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'joined_on' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
            'referred_by_member_id' => ['nullable', 'integer', TRule::exists('members', 'id')],
            'assigned_trainer_id' => ['nullable', 'integer', TRule::exists('employees', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => "The member's name is required.",
            'date_of_birth.before' => 'The date of birth must be in the past.',
            'photo.image' => 'The photo must be an image file.',
        ];
    }
}
