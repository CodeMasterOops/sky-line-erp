<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use Illuminate\Http\Request;
use App\Models\CompanyCategory;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleCache;
use App\Services\Modules\CompanyModuleService;
use App\Http\Resources\SuperAdmin\CompanyCategoryResource;
use App\Http\Requests\Api\SuperAdmin\CompanyCategoryRequest;

/**
 * The industry catalogue. A category's module list is the *starting point* for
 * companies in that industry; editing it never rewrites companies that already
 * exist, which is why applying defaults to a live company is a separate,
 * deliberate action (CompanyModuleController::applyCategory).
 *
 * Editing the list does, however, change what companies *without* explicit
 * rows resolve to, so every edit drops those companies' cached resolution —
 * see propagate().
 */
class CompanyCategoryController extends Controller
{
    public function __construct(private readonly CompanyModuleService $modules) {}

    public function index(Request $request)
    {
        $categories = CompanyCategory::query()
            ->with('modules')
            ->withCount('companies')
            ->filter($request->query())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->query('limit', 25));

        return CompanyCategoryResource::collection($categories);
    }

    public function store(CompanyCategoryRequest $request)
    {
        $data = $request->validated();
        $moduleKeys = $this->closedModuleSelection($data);

        $category = DB::transaction(function () use ($data, $moduleKeys): CompanyCategory {
            if (! empty($data['is_default'])) {
                CompanyCategory::query()->update(['is_default' => false]);
            }

            $category = CompanyCategory::create($data);
            $this->syncModules($category, $moduleKeys);

            return $category;
        });

        return response()->json([
            'data' => CompanyCategoryResource::make($category->load('modules')),
            'message' => 'Company category created successfully.',
        ], 201);
    }

    public function show(CompanyCategory $companyCategory)
    {
        return CompanyCategoryResource::make(
            $companyCategory->load('modules')->loadCount('companies')
        );
    }

    public function update(CompanyCategoryRequest $request, CompanyCategory $companyCategory)
    {
        $data = $request->validated();
        $moduleKeys = $this->closedModuleSelection($data);

        DB::transaction(function () use ($data, $moduleKeys, $companyCategory): void {
            if (! empty($data['is_default'])) {
                CompanyCategory::query()->where('id', '!=', $companyCategory->id)->update(['is_default' => false]);
            }

            $companyCategory->update($data);

            if (array_key_exists('modules', $data)) {
                $this->syncModules($companyCategory, $moduleKeys);
            }
        });

        return response()->json([
            'data' => CompanyCategoryResource::make($companyCategory->fresh()->load('modules')),
            'message' => 'Company category updated successfully.',
        ]);
    }

    public function destroy(CompanyCategory $companyCategory)
    {
        if ($companyCategory->is_default) {
            return response()->json([
                'message' => 'The default category cannot be deleted.',
            ], 422);
        }

        if ($companyCategory->companies()->exists()) {
            return response()->json([
                'message' => 'This category cannot be deleted because companies are assigned to it.',
            ], 422);
        }

        $companyCategory->delete();

        return response()->json([
            'message' => 'Company category deleted successfully.',
        ]);
    }

    /**
     * Close the selection over module requirements, so a category offering
     * `sales` also ships `accounting` and `inventory`. Without this the
     * resolver would immediately switch `sales` back off and the category
     * would look broken.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function closedModuleSelection(array $data): array
    {
        return $this->modules->closure($data['modules'] ?? []);
    }

    /**
     * @param  list<string>  $moduleKeys
     */
    private function syncModules(CompanyCategory $category, array $moduleKeys): void
    {
        $category->modules()->whereNotIn('module_key', $moduleKeys)->delete();

        foreach ($moduleKeys as $index => $moduleKey) {
            $category->modules()->updateOrCreate(
                ['module_key' => $moduleKey],
                ['is_default_enabled' => true, 'sort_order' => $index],
            );
        }

        $this->propagate($category);
    }

    /**
     * Push a category edit out to the companies that follow it.
     *
     * A company with no explicit `company_modules` rows resolves through its
     * category, and that resolution is cached with `forever`. Editing the
     * category therefore changed *nothing* for exactly the companies the edit
     * was meant for, until some unrelated row write happened to drop their
     * entry. Dropping the cache is enough — resolution recomputes on the next
     * request, and companies with explicit rows are unaffected by design.
     */
    private function propagate(CompanyCategory $category): void
    {
        $cache = app(ModuleCache::class);

        $category->companies()
            ->select('id')
            ->chunkById(200, function ($companies) use ($cache): void {
                foreach ($companies as $company) {
                    $cache->forget((int) $company->id);
                }
            });
    }
}
