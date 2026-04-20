<?php

namespace App\Http\Controllers\Api\Admin\HR;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\SalaryComponent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SalaryComponentResource;
use App\Http\Requests\Api\Admin\SalaryComponentRequest;

class SalaryComponentController extends Controller
{
    /**
     * @Permissions("list_salary_component", group="hr", desc="List Salary Component")
     */
    public function index(Request $request)
    {
        $components = SalaryComponent::paginate($request->limit ?? 50);

        return SalaryComponentResource::collection($components);
    }

    /**
     * @Permissions("create_salary_component", group="hr", desc="Create Salary Component")
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
     * @Permissions("show_salary_component", group="hr", desc="Show Salary Component")
     */
    public function show(SalaryComponent $salaryComponent)
    {
        return SalaryComponentResource::make($salaryComponent);
    }

    /**
     * @Permissions("edit_salary_component", group="hr", desc="Edit Salary Component")
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
     * @Permissions("delete_salary_component", group="hr", desc="Delete Salary Component")
     */
    public function destroy(SalaryComponent $salaryComponent)
    {
        $salaryComponent->delete();

        return response()->json([
            'message' => 'Salary Component Deleted Successfully',
        ]);
    }
}
