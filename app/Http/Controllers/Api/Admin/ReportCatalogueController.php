<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportCatalogue;

/**
 * The Reports hub's contents, filtered by what this user may see AND what this
 * company runs.
 *
 * The hub used to carry its own catalogue and filter it on permissions alone,
 * so a company that had switched a module off still saw every one of that
 * module's reports and got bounced on the way in. Both filters now happen
 * server-side against `config/reports.php` — the same catalogue the pinned
 * reports validate against.
 *
 * Reports themselves stay gated where they always were: the `module`
 * middleware on their endpoints and the router's `meta.module` guard. This
 * decides what is worth *offering*.
 */
class ReportCatalogueController extends Controller
{
    public function __construct(private readonly ReportCatalogue $catalogue) {}

    public function __invoke()
    {
        return response()->json([
            'data' => $this->catalogue->visibleCategories(),
        ]);
    }
}
