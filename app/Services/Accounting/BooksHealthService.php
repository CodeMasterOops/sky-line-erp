<?php

namespace App\Services\Accounting;

use Illuminate\Http\Request;

/**
 * A real-time, company-wide "are my books sound?" snapshot for an admin health
 * panel. Rolls up the standing GL-integrity signals — unbalanced journals,
 * unposted documents, and the four control-account reconciliations (AR, AP,
 * inventory, VAT) — into one severity-tagged result with an overall status.
 *
 * Distinct from YearEndCloseService::readiness (which gates closing a *specific*
 * fiscal year and adds period/net-profit context); this is an always-on,
 * not-fiscal-year-bound monitoring view.
 */
class BooksHealthService
{
    public function __construct(
        private readonly AccountReportService $accountReportService,
    ) {}

    /**
     * @return array{status:string, healthy:bool, checks:list<array<string,mixed>>, issues:list<string>}
     */
    public function snapshot(Request $request): array
    {
        $unbalanced = $this->accountReportService->unbalancedJournals($request);
        $unposted = $this->accountReportService->unpostedDocuments($request);
        $arAp = $this->accountReportService->arApReconciliation($request);
        $inventory = $this->accountReportService->inventoryGlReconciliation($request);
        $vat = $this->accountReportService->vatReturnReconciliation($request);

        $unbalancedCount = (int) ($unbalanced['summary']['count'] ?? 0);
        $unpostedCount = (int) ($unposted['summary']['total_count'] ?? 0);

        $checks = [
            $this->check(
                'unbalanced_journals',
                'Unbalanced journals',
                $unbalancedCount === 0,
                'critical',
                ['count' => $unbalancedCount],
            ),
            $this->check(
                'unposted_documents',
                'Approved documents missing a GL journal',
                $unpostedCount === 0,
                'warning',
                ['count' => $unpostedCount],
            ),
            $this->controlCheck('ar_reconciliation', 'AR control vs sub-ledger', $arAp['ar'] ?? null),
            $this->controlCheck('ap_reconciliation', 'AP control vs sub-ledger', $arAp['ap'] ?? null),
            $this->controlCheck('inventory_reconciliation', 'Inventory control vs stock valuation', $inventory),
            $this->controlCheck('vat_reconciliation', 'VAT return vs GL VAT account', $vat),
        ];

        $failing = array_values(array_filter($checks, static fn (array $check): bool => ! $check['ok']));
        $hasCritical = (bool) array_filter($failing, static fn (array $check): bool => $check['severity'] === 'critical');

        $status = match (true) {
            $failing === [] => 'healthy',
            $hasCritical => 'critical',
            default => 'warning',
        };

        return [
            'status' => $status,
            'healthy' => $failing === [],
            'checks' => $checks,
            'issues' => array_map(static fn (array $check): string => $check['label'], $failing),
        ];
    }

    /**
     * @param  array<string,mixed>  $detail
     * @return array{key:string, label:string, ok:bool, severity:string, detail:array<string,mixed>}
     */
    private function check(string $key, string $label, bool $ok, string $severity, array $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'severity' => $severity,
            'detail' => $detail,
        ];
    }

    /**
     * A control-account reconciliation is "ok" when it is unconfigured (not
     * tracked in the GL — not a problem) or reconciled within tolerance.
     *
     * @param  array{account_configured?:bool, reconciled?:bool, delta?:float}|null  $control
     * @return array{key:string, label:string, ok:bool, severity:string, detail:array<string,mixed>}
     */
    private function controlCheck(string $key, string $label, ?array $control): array
    {
        $configured = (bool) ($control['account_configured'] ?? false);
        $ok = ! $configured || (bool) ($control['reconciled'] ?? false);

        return $this->check($key, $label, $ok, 'warning', [
            'account_configured' => $configured,
            'delta' => $control['delta'] ?? 0,
        ]);
    }
}
