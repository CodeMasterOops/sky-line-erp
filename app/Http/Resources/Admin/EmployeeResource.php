<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code ?? '',
            'first_name' => $this->first_name ?? '',
            'last_name' => $this->last_name ?? '',
            'full_name' => $this->full_name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'gender' => $this->gender ?? '',
            'dob' => $this->dob?->format('Y-m-d'),
            'join_date' => $this->join_date?->format('Y-m-d'),
            'employment_type' => $this->employment_type ?? '',
            'employment_type_label' => $this->employment_type?->label() ?? '',
            'status' => $this->status ?? '',
            'status_label' => $this->status?->label() ?? '',
            'bank_name' => $this->bank_name ?? '',
            'bank_account_no' => $this->bank_account_no ?? '',
            'pan' => $this->pan ?? '',
            'tds_category' => $this->tds_category?->value ?? '',
            'tds_category_label' => $this->tds_category?->label() ?? '',
            'address' => $this->address ?? '',
            'photo' => $this->photo ?? '',
            'department_id' => $this->department_id,
            'designation_id' => $this->designation_id,
            'department' => $this->whenLoaded('department', fn () => DepartmentResource::make($this->department)),
            'designation' => $this->whenLoaded('designation', fn () => DesignationResource::make($this->designation)),
        ];
    }
}
