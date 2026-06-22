<?php

namespace App\Http\Requests\Api\Admin\Crm;

use App\Models\Party;
use App\Tenancy\TRule;
use App\Enums\TaskStatusEnum;
use App\Enums\TaskPriorityEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(TaskPriorityEnum::class)],
            'status' => ['nullable', Rule::enum(TaskStatusEnum::class)],
            'assigned_to_user_id' => ['nullable', 'integer', TRule::exists('users', 'id')],
            'party_id' => ['nullable', 'integer', TRule::exists('parties', 'id')],
            'due_date' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Resolve the polymorphic taskable attributes from an optional party_id.
     *
     * @return array{taskable_type: class-string<Party>, taskable_id: int}|array{}
     */
    public function taskableAttributes(): array
    {
        if (! $this->filled('party_id')) {
            return [];
        }

        return [
            'taskable_type' => Party::class,
            'taskable_id' => (int) $this->input('party_id'),
        ];
    }
}
