<?php

use App\Models\Bom;
use App\Models\Tag;
use App\Models\Tax;
use App\Models\Bill;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\Ward;
use App\Models\Batch;
use App\Models\Brand;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\Cheque;
use App\Models\Member;
use App\Models\Palika;
use App\Models\Account;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Expense;
use App\Models\GrnItem;
use App\Models\Holiday;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Models\BillItem;
use App\Models\Currency;
use App\Models\Discount;
use App\Models\District;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\Province;
use App\Models\TaxGroup;
use App\Models\Attribute;
use App\Models\DebitNote;
use App\Models\LeaveType;
use App\Models\Quotation;
use App\Models\Warehouse;
use App\Models\Attendance;
use App\Models\BranchUser;
use App\Models\BudgetLine;
use App\Models\CreditNote;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\LandedCost;
use App\Models\Membership;
use App\Models\PayrollRun;
use App\Models\PosSession;
use App\Models\ProductTax;
use App\Models\SalesOrder;
use App\Models\StockLayer;
use App\Models\SuperAdmin;
use App\Models\BankAccount;
use App\Models\CrmActivity;
use App\Models\Designation;
use App\Models\ExpenseItem;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use App\Models\PaymentMode;
use App\Models\PayslipItem;
use App\Models\TaxTemplate;
use App\Models\AccountGroup;
use App\Models\BomOperation;
use App\Models\DamageReport;
use App\Models\PosHeldOrder;
use App\Models\SerialNumber;
use App\Models\Subscription;
use App\Models\TdsDeduction;
use App\Models\WorkSchedule;
use App\Models\CompanyModule;
use App\Models\ContactPerson;
use App\Models\DebitNoteItem;
use App\Models\MemberCheckIn;
use App\Models\PurchaseOrder;
use App\Models\QuotationItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TdsReceivable;
use App\Models\AccountSetting;
use App\Models\AttributeValue;
use App\Models\CreditNoteItem;
use App\Models\CrmLeadProfile;
use App\Models\MembershipPlan;
use App\Models\ProductVariant;
use App\Models\SalesOrderItem;
use App\Models\TaxGroupMember;
use App\Models\UnitConversion;
use App\Models\CompanyCategory;
use App\Models\CustomerAdvance;
use App\Models\DataTransferJob;
use App\Models\DataTransferRow;
use App\Models\DeliveryChallan;
use App\Models\PosCashMovement;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\StockAdjustment;
use App\Models\AccountingPeriod;
use App\Models\BankMatchingRule;
use App\Models\DamageReportItem;
use App\Models\ImportValueAlias;
use App\Models\LeaveApplication;
use App\Models\MembershipFreeze;
use App\Models\RecurringJournal;
use App\Models\SecurityActivity;
use App\Models\StockReservation;
use App\Models\BankStatementLine;
use App\Models\GoodsReceivedNote;
use App\Models\OpeningStockEntry;
use App\Models\PartyTaxExemption;
use App\Models\PaymentAllocation;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiptAllocation;
use App\Models\StockTransferItem;
use App\Models\AdvanceApplication;
use App\Models\BankReconciliation;
use App\Models\CompanyModuleEvent;
use App\Models\FixedAssetCategory;
use App\Models\StockMovementLayer;
use App\Models\BankStatementImport;
use App\Models\DataTransferMapping;
use App\Models\DeliveryChallanItem;
use App\Models\SalaryStructureItem;
use App\Models\StockAdjustmentItem;
use App\Models\DataTransferSchedule;
use App\Models\LandedCostAllocation;
use App\Models\RecurringJournalItem;
use App\Models\CompanyCategoryModule;
use App\Models\OpeningStockEntryItem;
use App\Models\ReconciliationAuditLog;
use App\Models\TdsCertificateSequence;
use App\Models\ProductionOrderOperation;
use App\Models\InventoryValuationSnapshot;
use App\Models\ProductionOrderConsumption;

/*
|--------------------------------------------------------------------------
| Tenancy / Branch Isolation Classification Manifest
|--------------------------------------------------------------------------
|
| Phase 0 of the Branch Isolation plan
| (docs/branch-isolation-complete-implementation-plan.md).
|
| Every Eloquent model MUST appear in exactly one EXCLUSIVE bucket below:
|   - branch_owned                (Tier A, scoped by branch_id)
|   - branch_owned_via_parent     (Tier A child, scoped through its parent)
|   - company_global              (Tier B, master/config, company_id only)
|   - company_global_via_parent   (Tier B child)
|   - system_global               (Tier C, no tenant scope)
|   - unscoped_special            (deliberate exceptions, documented)
|
| tests/Feature/Tenancy/ModelClassificationTest.php enforces completeness
| and (for already-migrated models) trait correctness. The two ANNOTATION
| lists at the bottom (pending_branch_migration, flagged_for_review) are the
| allow-list: a Tier A model listed in pending_branch_migration is exempt
| from the BranchTenant-trait assertion until its migration phase ships.
|
| Product decisions frozen 2026-06-24 (§11 of the plan):
|   - Product / ProductVariant  -> Tier A (per-branch catalog)
|   - Party (+ contacts/leads)  -> Tier A (branch-owned)
|   - Warehouse, Employee       -> Tier A (branch-owned)
|   - Code uniqueness           -> per-company (company_id) for ALL codes
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Tier A — Branch-Owned (scoped by branch_id via BranchTenant)
    |--------------------------------------------------------------------------
    | End-state list. Models also present in `pending_branch_migration` do
    | not yet carry branch_id / the trait and are exempt from enforcement.
    */
    'branch_owned' => [
        // Already branch-scoped (BranchTenant applied)
        Bill::class,
        CreditNote::class,
        CrmActivity::class,
        DamageReport::class,
        DebitNote::class,
        DeliveryChallan::class,
        Expense::class,
        FollowUp::class,
        GoodsReceivedNote::class,
        Invoice::class,
        Journal::class,
        Note::class,
        OpeningStockEntry::class,
        Payment::class,
        PosHeldOrder::class,
        PosSession::class,
        PurchaseOrder::class,
        Quotation::class,
        Receipt::class,
        SalesOrder::class,
        Stock::class,
        StockAdjustment::class,
        StockMovement::class,
        StockTransfer::class,
        Task::class,

        // Has branch_id column already, trait missing (Phase 1)
        BankAccount::class,
        BankReconciliation::class,
        Budget::class,
        DataTransferJob::class,

        // Phase 2a — master data (branch_id + BranchTenant added 2026-06-24)
        Product::class,
        ProductVariant::class,
        Party::class,
        Warehouse::class,
        Employee::class,

        // Phase 2b — transaction / state tables (own branch_id, added 2026-06-24)
        CustomerAdvance::class,
        Attendance::class,
        PayrollRun::class,
        LeaveApplication::class,
        AdvanceApplication::class,
        FixedAsset::class,
        ProductionOrder::class,
        LandedCost::class,
        Cheque::class,
        TdsDeduction::class,
        TdsReceivable::class,
        RecurringJournal::class,
        StockLayer::class,
        StockReservation::class,
        InventoryValuationSnapshot::class,
        PosCashMovement::class,
        SerialNumber::class,
        Batch::class,
        BankStatementImport::class,

        // Phase 5 — Gym module. Branch-owned to match Party and Product: a
        // chain gets a per-branch member register and per-branch pricing.
        Member::class,
        MembershipPlan::class,
        Membership::class,
        MemberCheckIn::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier A children — scoped through their parent (no own branch_id)
    |--------------------------------------------------------------------------
    | value = the parent model whose branch_id governs this record.
    */
    'branch_owned_via_parent' => [
        BillItem::class => Bill::class,
        BudgetLine::class => Budget::class,
        ContactPerson::class => Party::class,
        CreditNoteItem::class => CreditNote::class,
        DamageReportItem::class => DamageReport::class,
        DataTransferRow::class => DataTransferJob::class,
        DebitNoteItem::class => DebitNote::class,
        DeliveryChallanItem::class => DeliveryChallan::class,
        ExpenseItem::class => Expense::class,
        GrnItem::class => GoodsReceivedNote::class,
        InvoiceItem::class => Invoice::class,
        JournalItem::class => Journal::class,
        LandedCostAllocation::class => LandedCost::class,
        OpeningStockEntryItem::class => OpeningStockEntry::class,
        PartyTaxExemption::class => Party::class,
        PaymentAllocation::class => Payment::class,
        Payslip::class => PayrollRun::class,
        PayslipItem::class => Payslip::class,
        ProductTax::class => Product::class,
        ProductionOrderConsumption::class => ProductionOrder::class,
        ProductionOrderOperation::class => ProductionOrder::class,
        CrmLeadProfile::class => Party::class,
        PurchaseOrderItem::class => PurchaseOrder::class,
        QuotationItem::class => Quotation::class,
        ReceiptAllocation::class => Receipt::class,
        ReconciliationAuditLog::class => BankReconciliation::class,
        RecurringJournalItem::class => RecurringJournal::class,
        SalesOrderItem::class => SalesOrder::class,
        StockAdjustmentItem::class => StockAdjustment::class,
        StockMovementLayer::class => StockMovement::class,
        StockTransferItem::class => StockTransfer::class,
        BankStatementLine::class => BankStatementImport::class,

        // Phase 7 — Gym
        MembershipFreeze::class => Membership::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier B — Company-Global / Master Data (company_id only, MultiTenant)
    |--------------------------------------------------------------------------
    | Intentionally shared across all branches of a company.
    */
    'company_global' => [
        Account::class,
        CompanyModule::class,
        CompanyModuleEvent::class,
        AccountGroup::class,
        AccountSetting::class,
        AccountingPeriod::class,
        Attribute::class,
        AttributeValue::class,
        AuditLog::class,
        BankMatchingRule::class,
        Bom::class,
        BomOperation::class,
        Branch::class,
        BranchUser::class,
        Brand::class,
        DataTransferMapping::class,
        DataTransferSchedule::class,
        Department::class,
        Designation::class,
        Discount::class,
        FiscalYear::class,
        FixedAssetCategory::class,
        Holiday::class,
        ImportValueAlias::class,
        LeaveType::class,
        PaymentMode::class,
        ProductCategory::class,
        Role::class,
        SalaryComponent::class,
        SecurityActivity::class,
        SalaryStructure::class,
        Subscription::class,
        Tag::class,
        Tax::class,
        TaxGroup::class,
        TaxTemplate::class,
        TdsCertificateSequence::class,
        Unit::class,
        UnitConversion::class,
        WorkSchedule::class,
    ],

    'company_global_via_parent' => [
        BomItem::class => Bom::class,
        SalaryStructureItem::class => SalaryStructure::class,
        TaxGroupMember::class => TaxGroup::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier C — System-Global reference data (no tenant scope)
    |--------------------------------------------------------------------------
    */
    'system_global' => [
        Company::class,
        CompanyCategory::class,
        CompanyCategoryModule::class,
        Currency::class,
        District::class,
        Lead::class,
        Palika::class,
        Plan::class,
        Province::class,
        Setting::class,
        SuperAdmin::class,
        Ward::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deliberate exceptions (documented; never auto-scoped)
    |--------------------------------------------------------------------------
    | value = reason the model is intentionally outside the scope buckets.
    */
    'unscoped_special' => [
        User::class => 'Belongs to a home company but accesses many branches via the branch_users pivot; must NOT be branch-scoped or admins/multi-branch users lose access. Filter through User::branches() instead.',
    ],

    /*
    |--------------------------------------------------------------------------
    | ANNOTATION: Tier A models still needing a branch_id migration
    |--------------------------------------------------------------------------
    | Allow-list for the trait-enforcement assertion. Remove each entry as its
    | migration + BranchTenant trait ship (Phases 1-2), at which point the test
    | begins enforcing branch scoping for that model.
    */
    'pending_branch_migration' => [
        // Phase 1, 2a, 2b all DONE 2026-06-24. Every branch-owned model now
        // carries branch_id + BranchTenant and is enforced by the classification
        // test. This list is intentionally empty; add a model here only if a
        // future Tier A entity is introduced before its migration ships.
    ],

    /*
    |--------------------------------------------------------------------------
    | ANNOTATION: Entities needing an architecture/product review
    |--------------------------------------------------------------------------
    | value = the open question. Not exclusive buckets; cross-references the
    | classification above.
    */
    'flagged_for_review' => [
        Discount::class => 'No company_id column found — currently global. Confirm it should be company-scoped (Tier B) and add the column.',
        FiscalYear::class => 'No company_id column found. Confirm company-scoped vs system reference.',
        ProductCategory::class => 'Kept Tier B (shared taxonomy) despite Product being per-branch. Confirm categories stay shared.',
        Brand::class => 'Kept Tier B (shared taxonomy) despite Product being per-branch. Confirm brands stay shared.',
        Bom::class => 'Kept Tier B (shared recipe master). Confirm BOMs are shared while ProductionOrders are branch-owned.',
        BankMatchingRule::class => 'Kept Tier B (config). Confirm reconciliation rules are shared, not per-branch.',
        AccountingPeriod::class => 'Kept Tier B (company-wide accounting calendar). Confirm periods are not per-branch.',
        DataTransferSchedule::class => 'Kept Tier B. Confirm scheduled exports are company-level, not branch-level.',
        ImportValueAlias::class => 'Has company_id but does NOT use MultiTenant — company-level leak risk. Add the trait during the company-scope review.',
        Subscription::class => 'Has company_id but does NOT use MultiTenant — company-level leak risk. Add the trait during the company-scope review.',
        TdsCertificateSequence::class => 'Has company_id but does NOT use MultiTenant — company-level leak risk (sequence numbering). Add the trait during the company-scope review.',
        TaxTemplate::class => 'No company_id column and no MultiTenant. Confirm whether tax templates are global reference data or company-scoped.',
        BankAccount::class => 'Now branch-owned (Phase 1). Some businesses share ONE bank account across branches — if needed, expose it via the §5.4 explicit cross-branch share mechanism rather than relaxing the scope. Existing null-branch accounts were backfilled to head office.',
    ],

];
