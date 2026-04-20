<?php

namespace App\Http\Controllers\Api\Admin\HR;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SalaryStructureResource;
use App\Http\Requests\Api\Admin\SalaryStructureRequest;

class SalaryStructureController extends Controller
{
    /**
     * @Permissions("list_salary_structure", group="hr", desc="List Salary Structures")
     */
    public function index(Request $request)
    {
        $structures = SalaryStructure::with(['employee', 'items.salaryComponent'])
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->latest()
            ->paginate($request->limit ?? 25);

        return SalaryStructureResource::collection($structures);
    }

    /**
     * @Permissions("create_salary_structure", group="hr", desc="Create Salary Structure")
     */
    public function store(SalaryStructureRequest $request)
    {
        SalaryStructure::where('employee_id', $request->employee_id)
            ->update(['is_active' => false]);

        $structure = SalaryStructure::create($request->safe()->except('items'));

        foreach ($request->items as $item) {
            SalaryStructureItem::create(array_merge($item, ['salary_structure_id' => $structure->id]));
        }

        return response()->json([
            'data' => SalaryStructureResource::make($structure->load(['employee', 'items.salaryComponent'])),
            'message' => 'Salary Structure Assigned Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_salary_structure", group="hr", desc="Show Salary Structure")
     */
    public function show(SalaryStructure $salaryStructure)
    {
        return SalaryStructureResource::make($salaryStructure->load(['employee', 'items.salaryComponent']));
    }

    /**
     * @Permissions("edit_salary_structure", group="hr", desc="Edit Salary Structure")
     */
    public function update(SalaryStructureRequest $request, SalaryStructure $salaryStructure)
    {
        $salaryStructure->update($request->safe()->except('items'));
        $salaryStructure->items()->delete();

        foreach ($request->items as $item) {
            SalaryStructureItem::create(array_merge($item, ['salary_structure_id' => $salaryStructure->id]));
        }

        return response()->json([
            'data' => SalaryStructureResource::make($salaryStructure->load(['employee', 'items.salaryComponent'])),
            'message' => 'Salary Structure Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_salary_structure", group="hr", desc="Delete Salary Structure")
     */
    public function destroy(SalaryStructure $salaryStructure)
    {
        $salaryStructure->items()->delete();
        $salaryStructure->delete();

        return response()->json([
            'message' => 'Salary Structure Deleted Successfully',
        ]);
    }
}
