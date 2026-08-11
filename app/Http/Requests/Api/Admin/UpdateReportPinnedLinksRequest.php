<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use App\Services\Reports\ReportCatalogue;

/**
 * Pinned reports carry the same two filters as the hub itself: a user may only
 * pin a report they can currently open.
 *
 * Deliberately a *write* rule only. Pins already stored for a report that later
 * goes out of reach — a module switched off, a permission revoked — are left
 * alone and simply not rendered, so switching the module back on brings the
 * user's pins back exactly as they were. Same reversibility guarantee as the
 * modules themselves.
 */
class UpdateReportPinnedLinksRequest extends UpdatePinnedLinksRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['links.*'][] = Rule::in(app(ReportCatalogue::class)->visibleRouteNames());

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'links.*.in' => 'That report is not available for your company.',
        ]);
    }
}
