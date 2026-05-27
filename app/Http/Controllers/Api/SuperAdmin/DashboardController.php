<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('is_active', true)->count();
        $onboardedCompanies = Company::whereNotNull('onboarding_completed_at')->count();
        $companiesToday = Company::whereDate('created_at', today())->count();

        $thisMonthNew = Company::where('created_at', '>=', now()->startOfMonth())->count();
        $lastMonthNew = Company::whereBetween('created_at', [
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth(),
        ])->count();

        $totalAtLastMonthEnd = Company::where('created_at', '<=', now()->subMonth()->endOfMonth())->count();
        $activeAtLastMonthEnd = Company::where('is_active', true)
            ->where('created_at', '<=', now()->subMonth()->endOfMonth())
            ->count();
        $onboardedAtLastMonthEnd = Company::whereNotNull('onboarding_completed_at')
            ->where('onboarding_completed_at', '<=', now()->subMonth()->endOfMonth())
            ->count();

        return response()->json([
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'inactive_companies' => $totalCompanies - $activeCompanies,
            'onboarded_companies' => $onboardedCompanies,
            'companies_today' => $companiesToday,
            'total_users' => User::where('user_type', UserTypeEnum::ADMIN->value)->count(),
            'fiscal_years_count' => FiscalYear::count(),
            'total_earnings' => 0,
            'growth' => [
                'total_companies' => $this->percentChange($totalCompanies, $totalAtLastMonthEnd),
                'active_companies' => $this->percentChange($activeCompanies, $activeAtLastMonthEnd),
                'onboarded_companies' => $this->percentChange($onboardedCompanies, $onboardedAtLastMonthEnd),
                'total_earnings' => 0,
                'new_companies' => $this->percentChange($thisMonthNew, $lastMonthNew),
            ],
            'companies_from_last_month' => $thisMonthNew - $lastMonthNew,
            'chart_data' => [
                'weekly' => $this->weeklyCompanyChart(),
                'monthly' => $this->monthlyCompanyChart(),
                'sparklines' => $this->sparklineData(),
            ],
            'top_plans' => [],
        ]);
    }

    private function percentChange(int|float $current, int|float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * @return array{labels: list<string>, companies: list<int>}
     */
    private function weeklyCompanyChart(): array
    {
        $since = now()->subDays(6)->startOfDay();

        $countsByDate = Company::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $days = collect(range(0, 6))
            ->map(fn (int $i) => now()->subDays(6 - $i)->startOfDay());

        return [
            'labels' => $days->map(fn (Carbon $day) => $day->format('D'))->values()->all(),
            'companies' => $days->map(fn (Carbon $day) => (int) ($countsByDate[$day->toDateString()] ?? 0))->values()->all(),
        ];
    }

    /**
     * @return array{labels: list<string>, new_companies: list<int>, active_companies: list<int>}
     */
    private function monthlyCompanyChart(): array
    {
        $since = now()->subMonths(11)->startOfMonth();

        $companies = Company::query()
            ->where('created_at', '>=', $since)
            ->get(['created_at', 'is_active']);

        $newByMonth = $companies
            ->groupBy(fn (Company $company) => $company->created_at->format('Y-m'))
            ->map->count();

        $activeByMonth = $companies
            ->where('is_active', true)
            ->groupBy(fn (Company $company) => $company->created_at->format('Y-m'))
            ->map->count();

        $months = collect(range(0, 11))
            ->map(fn (int $i) => now()->subMonths(11 - $i)->format('Y-m'));

        return [
            'labels' => $months->map(fn (string $month) => Carbon::createFromFormat('Y-m', $month)->format('M Y'))->values()->all(),
            'new_companies' => $months->map(fn (string $month) => (int) ($newByMonth[$month] ?? 0))->values()->all(),
            'active_companies' => $months->map(fn (string $month) => (int) ($activeByMonth[$month] ?? 0))->values()->all(),
        ];
    }

    /**
     * @return array{total: list<int>, active: list<int>, onboarded: list<int>, earnings: list<int>}
     */
    private function sparklineData(): array
    {
        $since = now()->subDays(6)->startOfDay();

        $newByDate = Company::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $activeByDate = Company::query()
            ->where('created_at', '>=', $since)
            ->where('is_active', true)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $onboardedByDate = Company::query()
            ->whereNotNull('onboarding_completed_at')
            ->where('onboarding_completed_at', '>=', $since)
            ->selectRaw('DATE(onboarding_completed_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $days = collect(range(0, 6))
            ->map(fn (int $i) => now()->subDays(6 - $i)->toDateString());

        $mapCounts = fn ($counts) => $days
            ->map(fn (string $date) => (int) ($counts[$date] ?? 0))
            ->values()
            ->all();

        return [
            'total' => $mapCounts($newByDate),
            'active' => $mapCounts($activeByDate),
            'onboarded' => $mapCounts($onboardedByDate),
            'earnings' => array_fill(0, 7, 0),
        ];
    }
}
