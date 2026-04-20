<?php

namespace App\Services;

use Carbon\Carbon;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AccountReportService
{
    public function trialBalance(Request $request): array
    {
        [$startDate, $endDate, $fiscalYear] = $this->resolvePeriod($request);

        $groups = AccountGroup::with([
            'accounts',
            'childrenRecursive',
        ])
            ->whereNull('parent_id')
            ->get();

        $groups = $this->sortGroupTree($groups);

        $accountIds = $this->collectAccountIds($groups);
        $balances = $this->fetchBalances($accountIds, $startDate, $endDate);

        $rows = $groups
            ->map(fn (AccountGroup $group) => $this->transformGroup($group, $balances))
            ->values()
            ->all();

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'label' => sprintf(
                    'For the period %s to %s',
                    $startDate->format('d-m-Y'),
                    $endDate->format('d-m-Y')
                ),
            ],
            'fiscal_year' => $fiscalYear ? [
                'id' => $fiscalYear->id,
                'year_name' => $fiscalYear->year_name,
                'year_code' => $fiscalYear->year_code,
                'start_date' => $fiscalYear->start_date?->toDateString(),
                'end_date' => $fiscalYear->end_date?->toDateString(),
            ] : null,
            'rows' => $rows,
            'summary' => $this->summarizeRows($rows),
        ];
    }

    private function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        $company = auth('admin')->user()?->company?->loadMissing('fiscalYear');

        $fiscalYear = ! empty($validated['fiscal_year_id'])
            ? FiscalYear::find($validated['fiscal_year_id'])
            : $company?->fiscalYear;

        $startDate = ! empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : ($fiscalYear?->start_date?->copy()->startOfDay() ?? now()->startOfMonth()->startOfDay());

        $endDate = ! empty($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : ($fiscalYear?->end_date?->copy()->endOfDay() ?? now()->endOfDay());

        if ($startDate->gt($endDate)) {
            abort(422, 'Start date must be before or equal to end date.');
        }

        return [$startDate, $endDate, $fiscalYear];
    }

    private function sortGroupTree(Collection $groups): Collection
    {
        return $groups
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(function (AccountGroup $group) {
                $group->setRelation(
                    'childrenRecursive',
                    $this->sortGroupTree(collect($group->childrenRecursive))
                );

                $group->setRelation(
                    'accounts',
                    collect($group->accounts)
                        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->values()
                );

                return $group;
            });
    }

    private function collectAccountIds(Collection $groups): array
    {
        $accountIds = [];

        foreach ($groups as $group) {
            foreach (collect($group->accounts) as $account) {
                $accountIds[] = $account->id;
            }

            $children = collect($group->childrenRecursive);
            if ($children->isNotEmpty()) {
                $accountIds = array_merge($accountIds, $this->collectAccountIds($children));
            }
        }

        return array_values(array_unique($accountIds));
    }

    private function fetchBalances(array $accountIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($accountIds)) {
            return collect();
        }

        $companyId = auth('admin')->user()->company_id;

        return JournalItem::query()
            ->select('journal_items.account_id')
            ->selectRaw(
                'SUM(CASE WHEN journals.date < ? THEN journal_items.dr_amount ELSE 0 END) as opening_dr',
                [$startDate->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN journals.date < ? THEN journal_items.cr_amount ELSE 0 END) as opening_cr',
                [$startDate->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN journals.date BETWEEN ? AND ? THEN journal_items.dr_amount ELSE 0 END) as transaction_dr',
                [$startDate->toDateString(), $endDate->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN journals.date BETWEEN ? AND ? THEN journal_items.cr_amount ELSE 0 END) as transaction_cr',
                [$startDate->toDateString(), $endDate->toDateString()]
            )
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->whereIn('journal_items.account_id', $accountIds)
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereDate('journals.date', '<=', $endDate->toDateString())
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id')
            ->map(function ($row) {
                return [
                    'opening' => [
                        'dr' => (float) $row->opening_dr,
                        'cr' => (float) $row->opening_cr,
                    ],
                    'transaction' => [
                        'dr' => (float) $row->transaction_dr,
                        'cr' => (float) $row->transaction_cr,
                    ],
                ];
            });
    }

    private function transformGroup(AccountGroup $group, Collection $balances): array
    {
        $children = [];

        foreach (collect($group->childrenRecursive) as $childGroup) {
            $children[] = $this->transformGroup($childGroup, $balances);
        }

        foreach (collect($group->accounts) as $account) {
            $children[] = $this->transformAccount($account, $balances);
        }

        $totals = $this->aggregateRows($children);

        return [
            'key' => 'group-'.$group->id,
            'type' => 'group',
            'id' => $group->id,
            'name' => $group->name,
            'code' => $group->code,
            'label' => $group->name,
            'opening' => $totals['opening'],
            'transaction' => $totals['transaction'],
            'closing' => $totals['closing'],
            'children' => $children,
        ];
    }

    private function transformAccount($account, Collection $balances): array
    {
        $amounts = $balances->get($account->id, [
            'opening' => ['dr' => 0, 'cr' => 0],
            'transaction' => ['dr' => 0, 'cr' => 0],
        ]);

        return [
            'key' => 'account-'.$account->id,
            'type' => 'account',
            'id' => $account->id,
            'name' => $account->name,
            'code' => $account->code,
            'label' => trim(sprintf('%s (%s)', $account->name, $account->code)),
            'opening' => $amounts['opening'],
            'transaction' => $amounts['transaction'],
            'closing' => $this->closingColumns(
                (float) $amounts['opening']['dr'] + (float) $amounts['transaction']['dr'],
                (float) $amounts['opening']['cr'] + (float) $amounts['transaction']['cr']
            ),
            'children' => [],
        ];
    }

    private function aggregateRows(array $rows): array
    {
        $openingDr = 0;
        $openingCr = 0;
        $transactionDr = 0;
        $transactionCr = 0;
        $closingDr = 0;
        $closingCr = 0;

        foreach ($rows as $row) {
            $openingDr += (float) data_get($row, 'opening.dr', 0);
            $openingCr += (float) data_get($row, 'opening.cr', 0);
            $transactionDr += (float) data_get($row, 'transaction.dr', 0);
            $transactionCr += (float) data_get($row, 'transaction.cr', 0);
            $closingDr += (float) data_get($row, 'closing.dr', 0);
            $closingCr += (float) data_get($row, 'closing.cr', 0);
        }

        return [
            'opening' => [
                'dr' => round($openingDr, 2),
                'cr' => round($openingCr, 2),
            ],
            'transaction' => [
                'dr' => round($transactionDr, 2),
                'cr' => round($transactionCr, 2),
            ],
            'closing' => [
                'dr' => round($closingDr, 2),
                'cr' => round($closingCr, 2),
            ],
        ];
    }

    private function closingColumns(float $drAmount, float $crAmount): array
    {
        $difference = round($drAmount - $crAmount, 2);

        if ($difference > 0) {
            return [
                'dr' => $difference,
                'cr' => 0,
            ];
        }

        if ($difference < 0) {
            return [
                'dr' => 0,
                'cr' => abs($difference),
            ];
        }

        return [
            'dr' => 0,
            'cr' => 0,
        ];
    }

    private function summarizeRows(array $rows): array
    {
        return $this->aggregateRows($rows);
    }
}
