<?php

namespace App\Services\Reports;

use App\Services\Modules\ModuleGate;

/**
 * Read model over `config/reports.php`.
 *
 * One place decides which reports a user may be offered — the hub endpoint and
 * the pinned-report validator both come here, so a report can never be
 * unpinnable in one and pinnable in the other.
 *
 * Two filters, both applied: the user's permissions and the company's enabled
 * modules. Neither is the security gate (that is `checkRole` and the `module`
 * middleware on the report's own endpoint); this is what stops the hub
 * advertising doors that are locked.
 */
class ReportCatalogue
{
    public function __construct(private readonly ModuleGate $modules) {}

    /**
     * Categories with at least one visible report, in configured order.
     *
     * @return list<array{title: string, slug: string, description: string, icon: ?string, accent_class: string, items: list<array{label: string, name: string, module: ?string}>}>
     */
    public function visibleCategories(): array
    {
        $categories = [];

        foreach ($this->rawCategories() as $category) {
            $items = $this->visibleItems((array) ($category['items'] ?? []));

            if ($items === []) {
                continue;
            }

            $categories[] = [
                'title' => (string) ($category['title'] ?? ''),
                'slug' => (string) ($category['slug'] ?? ''),
                'description' => (string) ($category['description'] ?? ''),
                'icon' => $category['icon'] ?? null,
                'accent_class' => (string) ($category['accent_class'] ?? ''),
                'items' => $items,
            ];
        }

        return $categories;
    }

    /**
     * Every route name the current user may open, de-duplicated. A report can
     * appear under more than one category (Customer Ledger is both a Sales and
     * a Customer report), which is why this is a set rather than a count.
     *
     * @return list<string>
     */
    public function visibleRouteNames(): array
    {
        $names = [];

        foreach ($this->visibleCategories() as $category) {
            foreach ($category['items'] as $item) {
                $names[$item['name']] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Every route name in the catalogue, ignoring permissions and modules.
     * Used by the surface test to check the manifest against the registry.
     *
     * @return list<array{label: string, route: string, permission: ?string, module: ?string}>
     */
    public function allItems(): array
    {
        $items = [];

        foreach ($this->rawCategories() as $category) {
            foreach ((array) ($category['items'] ?? []) as $item) {
                $items[] = [
                    'label' => (string) ($item['label'] ?? ''),
                    'route' => (string) ($item['route'] ?? ''),
                    'permission' => $item['permission'] ?? null,
                    'module' => $item['module'] ?? null,
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rawCategories(): array
    {
        return array_values((array) config('reports.categories', []));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{label: string, name: string, module: ?string}>
     */
    private function visibleItems(array $items): array
    {
        $permitted = array_filter(
            $this->modules->filter($items),
            fn (array $item): bool => empty($item['permission']) || hasPermission($item['permission']),
        );

        return array_values(array_map(
            fn (array $item): array => [
                'label' => (string) $item['label'],
                'name' => (string) $item['route'],
                'module' => $item['module'] ?? null,
            ],
            $permitted,
        ));
    }
}
