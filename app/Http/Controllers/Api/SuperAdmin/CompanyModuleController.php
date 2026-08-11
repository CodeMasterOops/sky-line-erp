<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Company;
use Illuminate\Http\Request;
use App\Models\CompanyCategory;
use App\Models\CompanyModuleEvent;
use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;
use App\Services\Modules\ModuleImpactAnalyzer;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Api\SuperAdmin\ApplyCompanyCategoryRequest;
use App\Http\Requests\Api\SuperAdmin\UpdateCompanyModulesRequest;

/**
 * Per-company module control: the matrix, the switches, and the audit trail.
 *
 * The Super Admin has the final say — a manual decision here outranks the
 * company's category and even its plan (recorded as such, and surfaced in the
 * matrix as an override). What it cannot do is destroy anything: disabling a
 * module only closes its doors.
 */
class CompanyModuleController extends Controller
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly CompanyModuleService $modules,
    ) {}

    /**
     * What disabling this module would mean, before anyone clicks.
     *
     * Read-only and non-blocking: it changes nothing, and it never refuses a
     * toggle. Disabling remains lossless, so this is context — how much data
     * sits behind the module, what cascades with it, what work is mid-flight —
     * rather than a warning about destruction.
     */
    public function impact(Company $company, string $moduleKey)
    {
        if (! $this->registry->has($moduleKey)) {
            throw ValidationException::withMessages([
                'module_key' => ["Unknown module [{$moduleKey}]."],
            ]);
        }

        return response()->json([
            'data' => app(ModuleImpactAnalyzer::class)->analyze($company, $moduleKey),
        ]);
    }

    /**
     * The full matrix for one company, grouped as the registry orders it.
     */
    public function index(Company $company)
    {
        $states = $this->modules->resolve((int) $company->id);
        $plan = $company->plan()->first();
        $categoryDefaults = $company->category
            ? $this->modules->closure($company->category->defaultModuleKeys())
            : [];

        $data = [];

        foreach ($this->registry->all() as $key => $definition) {
            $state = $states[$key];

            $data[] = [
                'key' => $key,
                'name' => $definition['name'],
                'group' => $definition['group'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'enabled' => $state['enabled'],
                'locked' => $state['locked'],
                'source' => $state['source'],
                'reason' => $state['reason'],
                'requires' => $definition['requires'],
                'missing_requirements' => $state['missing_requirements'],
                'dependents' => $this->registry->dependentsOf($key),
                'is_category_default' => in_array($key, $categoryDefaults, true),
                'entitled_by_plan' => $plan === null || $plan->entitlesModule($key),
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'company_id' => $company->id,
                'company_name' => $company->company_name,
                'category' => $company->category?->only(['id', 'name', 'slug']),
                'plan' => $plan?->only(['id', 'name', 'slug']),
                'plan_modules' => $plan?->modules,
                'enabled' => $this->modules->enabledKeys((int) $company->id),
            ],
        ]);
    }

    /**
     * Set several modules at once. Each key is applied through the same service
     * the rest of the app uses, so requirements are pulled in, dependents are
     * protected, and every transition is audited.
     */
    public function update(UpdateCompanyModulesRequest $request, Company $company)
    {
        $actor = auth('super_admin')->user();
        $cascade = $request->boolean('cascade');
        $reason = $request->input('reason');

        $enabled = [];
        $disabled = [];

        foreach ($request->validated('modules') as $moduleKey => $shouldEnable) {
            if ($this->registry->get($moduleKey)['always_on']) {
                continue;
            }

            if ($shouldEnable) {
                $enabled = array_merge($enabled, $this->modules->enable($company, $moduleKey, $actor, $reason));

                continue;
            }

            $disabled = array_merge(
                $disabled,
                $this->modules->disable($company, $moduleKey, $actor, $reason, cascade: $cascade),
            );
        }

        return response()->json([
            'data' => [
                'enabled' => array_values(array_unique($enabled)),
                'disabled' => array_values(array_unique($disabled)),
                'modules' => $this->modules->enabledKeys((int) $company->id),
            ],
            'message' => $this->summarise($enabled, $disabled),
        ]);
    }

    /**
     * Move a company to a different industry and (optionally) apply its
     * defaults. Applying is enable-only unless `disable_others` is explicitly
     * requested — a live tenant should not lose navigation because someone
     * corrected its industry.
     */
    public function applyCategory(ApplyCompanyCategoryRequest $request, Company $company)
    {
        $actor = auth('super_admin')->user();
        $categoryId = $request->validated('company_category_id');

        if ($categoryId !== null && ! CompanyCategory::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages([
                'company_category_id' => ['The selected category does not exist.'],
            ]);
        }

        $changed = $this->modules->changeCategory(
            $company,
            $categoryId,
            applyDefaults: $request->boolean('apply_defaults', true),
            disableOthers: $request->boolean('disable_others'),
            actor: $actor,
        );

        return response()->json([
            'data' => [
                'category' => $company->category?->only(['id', 'name', 'slug']),
                'changed' => $changed,
                'modules' => $this->modules->enabledKeys((int) $company->id),
            ],
            'message' => $changed === []
                ? 'Category updated. No module changes were needed.'
                : 'Category updated and '.count($changed).' module(s) adjusted.',
        ]);
    }

    /**
     * The audit trail — who switched what, when and why.
     */
    public function events(Request $request, Company $company)
    {
        $events = CompanyModuleEvent::query()
            ->withoutGlobalScope('company_scope')
            ->where('company_id', $company->id)
            ->filter($request->query())
            ->with('actor')
            ->latest('id')
            ->paginate($request->query('limit', 25));

        $events->getCollection()->transform(fn (CompanyModuleEvent $event): array => [
            'id' => $event->id,
            'module_key' => $event->module_key,
            'module_name' => $this->registry->has($event->module_key)
                ? $this->registry->get($event->module_key)['name']
                : $event->module_key,
            'action' => $event->action->value,
            'action_label' => $event->action->label(),
            'reason' => $event->reason,
            'actor' => $event->actor?->name,
            'context' => $event->context,
            'created_at' => $event->created_at?->toIso8601String(),
        ]);

        return response()->json($events);
    }

    /**
     * Reset a company back to exactly what its category prescribes, dropping
     * accumulated manual overrides.
     */
    public function resetToCategory(Company $company)
    {
        if (! $company->category) {
            throw ValidationException::withMessages([
                'company_category_id' => ['This company has no category to reset to.'],
            ]);
        }

        $changed = $this->modules->syncFromCategory($company, disableOthers: true, actor: auth('super_admin')->user());

        return response()->json([
            'data' => [
                'changed' => $changed,
                'modules' => $this->modules->enabledKeys((int) $company->id),
            ],
            'message' => 'Modules reset to the category defaults.',
        ]);
    }

    /**
     * @param  list<string>  $enabled
     * @param  list<string>  $disabled
     */
    private function summarise(array $enabled, array $disabled): string
    {
        if ($enabled === [] && $disabled === []) {
            return 'No module changes were needed.';
        }

        $parts = [];

        if ($enabled !== []) {
            $parts[] = count(array_unique($enabled)).' enabled';
        }

        if ($disabled !== []) {
            $parts[] = count(array_unique($disabled)).' disabled';
        }

        return 'Modules updated: '.implode(', ', $parts).'.';
    }
}
