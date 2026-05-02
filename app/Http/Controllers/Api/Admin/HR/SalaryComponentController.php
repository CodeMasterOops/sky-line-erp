<?php

namespace App\Http\Controllers\Api\Admin\HR;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\SalaryComponent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HR\SalaryComponentResource;
use App\Http\Requests\Api\Admin\HR\SalaryComponentRequest;

class SalaryComponentController extends Controller
{
    /**
     * @Permissions("list_salary_component", group="salary_component", desc="List Salary Component")
     */
    public function index(Request $request)
    {
        $components = SalaryComponent::paginate($request->limit ?? 50);

        return SalaryComponentResource::collection($components);
    }

    /**
     * @Permissions("create_salary_component", group="salary_component", desc="Create Salary Component")
     */
    public function store(SalaryComponentRequest $request)
    {
        $component = SalaryComponent::create($request->validated());

        return response()->json([
            'data' => SalaryComponentResource::make($component),
            'message' => 'Salary Component Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_salary_component", group="salary_component", desc="Show Salary Component")
     */
    public function show(SalaryComponent $salaryComponent)
    {
        return SalaryComponentResource::make($salaryComponent);
    }

    /**
     * @Permissions("edit_salary_component", group="salary_component", desc="Edit Salary Component")
     */
    public function update(SalaryComponentRequest $request, SalaryComponent $salaryComponent)
    {
        $salaryComponent->update($request->validated());

        return response()->json([
            'data' => SalaryComponentResource::make($salaryComponent),
            'message' => 'Salary Component Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_salary_component", group="salary_component", desc="Delete Salary Component")
     */
    public function destroy(SalaryComponent $salaryComponent)
    {
        $salaryComponent->delete();

        return response()->json([
            'message' => 'Salary Component Deleted Successfully',
        ]);
    }
}
