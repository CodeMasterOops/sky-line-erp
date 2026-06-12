<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\BranchUser;
use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Policies\BranchPolicy;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use App\Observers\BranchUserObserver;
use App\Services\BranchAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Observers\StockMovementObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureHttpsUrls();

        Relation::morphMap([
            'bill' => Bill::class,
            'expense' => Expense::class,
        ]);

        StockMovement::observe(StockMovementObserver::class);
        BranchUser::observe(BranchUserObserver::class);

        $this->registerBranchGates();

        $this->configureRateLimiting();

        Event::listen(MigrationsEnded::class, function () {
            Cache::forget('allTables');
            Cache::forget(\App\Http\Controllers\Api\Admin\UserManagement\PermissionController::PERMISSION_MAP_CACHE_KEY);
        });
    }

    /**
     * Force HTTPS for generated URLs when behind TLS-terminating proxies (e.g. Coolify/Traefik).
     */
    protected function configureHttpsUrls(): void
    {
        $forceHttps = $this->app->environment('production')
            || filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL);

        if (! $forceHttps) {
            return;
        }

        URL::forceScheme('https');

        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'http://')) {
            $httpsUrl = 'https://'.substr($appUrl, 7);
            config(['app.url' => $httpsUrl]);
            URL::forceRootUrl($httpsUrl);
        }
    }

    protected function registerBranchGates(): void
    {
        Gate::policy(Branch::class, BranchPolicy::class);

        Gate::define('access-branch', function ($user, Branch $branch): bool {
            return app(BranchAccessService::class)->canUserAccessBranch($user, $branch->id);
        });

        Gate::define('manage-branch-users', function ($user, Branch $branch): bool {
            return $user->isAdmin();
        });

        Gate::define('view-all-branches', function ($user): bool {
            return $user->isAdmin();
        });
    }

    /**
     * Register the application's named rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
