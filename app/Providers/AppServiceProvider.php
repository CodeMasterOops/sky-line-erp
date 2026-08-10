<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\BranchUser;
use App\Policies\BillPolicy;
use App\Policies\UserPolicy;
use Illuminate\Http\Request;
use App\Models\CompanyModule;
use App\Models\StockMovement;
use App\Policies\PartyPolicy;
use App\Policies\StockPolicy;
use App\Policies\BranchPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\JournalPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReceiptPolicy;
use App\Services\TenantService;
use App\Observers\StockObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use App\Observers\BranchUserObserver;
use App\Services\BranchAccessService;
use App\Services\Modules\ModuleCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Observers\CompanyModuleObserver;
use App\Observers\StockMovementObserver;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use App\Observers\BelongsToCompanyObserver;
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
        // Module registry / cache are read on nearly every request (route
        // gating, menus, permission catalogue), so they are resolved once per
        // request and memoise their parsed definitions.
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(ModuleCache::class);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Register Telescope tags for null-tenant-context admin API requests.
     * Entries tagged 'tenant:null-context' indicate a request that reached an
     * admin API route without a company being set in TenantService — a signal
     * that SetTenantContext was bypassed or the route is missing the middleware.
     */
    protected function registerTelescopeTenantTag(): void
    {
        if (! class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        \Laravel\Telescope\Telescope::tag(function (IncomingEntry $entry) {
            if (
                TenantService::companyId() === null
                && request()->is('api/admin/*')
                && auth('admin')->check()
            ) {
                return ['tenant:null-context'];
            }

            return [];
        });
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
        Stock::observe(StockObserver::class);

        // Registered in all environments including tests. In production: logs only.
        // In local: also throws RuntimeException. In tests: captures to
        // BelongsToCompanyObserver::$capturedLeaks for assertNoLeaks() assertions.
        //
        // Use event string (not Model::observe()) to avoid the model-boot race:
        // Model::observe() calls `new static`, which triggers bootMultiTenant() at
        // AppServiceProvider::boot() time — before test migrations run — so
        // columnExists() returns false and the MultiTenant creating hook is never
        // registered. String-based listeners are resolved lazily (no model boot).
        $models = [Invoice::class, Bill::class, Party::class, Payment::class];
        foreach ($models as $model) {
            Event::listen("eloquent.retrieved: {$model}", [BelongsToCompanyObserver::class, 'retrieved']);
        }

        // Invalidate the per-company module cache whenever a module row changes
        // outside CompanyModuleService. Event-string for the same model-boot
        // race reason described above (CompanyModule uses MultiTenant).
        Event::listen('eloquent.saved: '.CompanyModule::class, [CompanyModuleObserver::class, 'saved']);
        Event::listen('eloquent.deleted: '.CompanyModule::class, [CompanyModuleObserver::class, 'deleted']);

        // Auto-provision a CRM lead profile + timeline entry for any party of
        // type=lead. Event-string (not Party::observe()) for the same model-boot
        // race reason described above.
        Event::listen('eloquent.created: '.Party::class, [\App\Observers\PartyObserver::class, 'created']);
        Event::listen('eloquent.updated: '.Party::class, [\App\Observers\PartyObserver::class, 'updated']);

        $this->registerTelescopeTenantTag();

        $this->registerBranchGates();

        $this->configureRateLimiting();

        Event::listen(MigrationsEnded::class, function () {
            Cache::forget(allTablesCacheKey());
            Cache::forget(\App\Http\Controllers\Api\Admin\UserManagement\PermissionController::PERMISSION_MAP_CACHE_KEY);
            Cache::forget(\App\Services\PermissionRegistry::ENFORCED_PERMISSIONS_CACHE_KEY);
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Bill::class, BillPolicy::class);
        Gate::policy(Party::class, PartyPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Receipt::class, ReceiptPolicy::class);
        Gate::policy(Journal::class, JournalPolicy::class);
        Gate::policy(Stock::class, StockPolicy::class);

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

        RateLimiter::for('public.leads', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });

        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
