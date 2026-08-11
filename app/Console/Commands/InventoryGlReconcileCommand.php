<?php

namespace App\Console\Commands;

use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Services\TenantService;
use Illuminate\Console\Command;
use App\Console\Concerns\SkipsDisabledCompanies;
use App\Services\Accounting\StockMovementGlPostingService;

class InventoryGlReconcileCommand extends Command
{
    use SkipsDisabledCompanies;

    protected $signature = 'inventory:gl-reconcile
                            {--company= : Limit to a specific company ID}
                            {--dry-run : Report only, do not re-post missing journals}
                            {--limit=100 : Maximum movements to process per run}';

    protected $description = 'Find stock movements with no GL journal and optionally re-post them';

    public function __construct(private StockMovementGlPostingService $glPosting)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $companyId = $this->option('company');
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        // Re-posting journals is Inventory's work: a company that switched the
        // module off keeps its movements exactly as they are rather than having
        // them silently re-posted in the background.
        $companyIds = $this->companiesWithModule('inventory');

        if (! $this->reportModuleScope('inventory', $companyIds)) {
            return self::SUCCESS;
        }

        // Transfer movements intentionally skip GL; exclude them.
        $skipTypes = [ChangeTypeEnum::TRANSFER_IN->value, ChangeTypeEnum::TRANSFER_OUT->value];

        $query = StockMovement::withoutGlobalScopes()
            ->whereNull('gl_journal_id')
            ->where('total_cost', '>', 0)
            ->whereNotIn('type', $skipTypes)
            ->whereIn('company_id', $companyIds);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $movements = $query->limit($limit)->get();

        if ($movements->isEmpty()) {
            $this->info('No unposted inventory movements found.');

            return self::SUCCESS;
        }

        $this->info("Found {$movements->count()} movement(s) missing GL journals.");

        if ($dryRun) {
            $headers = ['ID', 'Company', 'Type', 'Direction', 'Total Cost', 'Created At'];
            $rows = $movements->map(fn ($m) => [
                $m->id,
                $m->company_id,
                $m->type->value,
                $m->direction->value,
                number_format((float) $m->total_cost, 2),
                $m->created_at?->toDateTimeString(),
            ])->all();
            $this->table($headers, $rows);
            $this->warn('Dry run — no journals posted.');

            return self::SUCCESS;
        }

        $posted = 0;
        $failed = 0;

        foreach ($movements as $movement) {
            try {
                TenantService::setCompanyId($movement->company_id);
                $this->glPosting->postFromMovement($movement);
                $posted++;
            } catch (\Throwable $e) {
                $this->warn("Movement #{$movement->id}: {$e->getMessage()}");
                $failed++;
            } finally {
                TenantService::reset();
            }
        }

        $this->info("Posted: {$posted} | Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
