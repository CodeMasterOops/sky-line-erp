<template>
    <div class="reports-hub">
        <PageHeader
            title="Reports"
            subtitle="Open a report by category. Links respect your role permissions."
            :hide-action-buttons="true"
        />

        <div class="reports-hub__search">
            <div class="search-input">
                <a class="btn-searchset" href="javascript:void(0);" tabindex="-1">
                    <i class="ti ti-search fs-14 feather-search"></i>
                </a>
                <input
                    v-model="searchQuery"
                    type="search"
                    class="form-control"
                    placeholder="Search reports…"
                />
                <button
                    v-if="searchQuery"
                    class="btn-clearsearch"
                    type="button"
                    @click="searchQuery = ''"
                >
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <p v-if="searchQuery" class="reports-hub__search-meta">
                {{ totalVisible }} report{{ totalVisible !== 1 ? 's' : '' }} found
            </p>
        </div>

        <div
            v-if="filteredCategories.length > 0"
            id="reportsHubAccordion"
            class="reports-hub__grid"
        >
            <div
                v-for="(cat, idx) in filteredCategories"
                :key="cat.slug"
                class="accordion-item reports-hub__category"
            >
                <h2 class="accordion-header" :id="`heading-${cat.slug}`">
                    <button
                        class="accordion-button reports-hub__toggle bg-white"
                        :class="{ collapsed: !isFirstAndNoSearch(idx) }"
                        type="button"
                        :data-bs-toggle="searchQuery ? undefined : 'collapse'"
                        :data-bs-target="searchQuery ? undefined : `#collapse-${cat.slug}`"
                        :aria-expanded="isFirstAndNoSearch(idx) || !!searchQuery"
                        :aria-controls="`collapse-${cat.slug}`"
                    >
                        <span
                            class="reports-hub__icon"
                            :class="cat.accentClass"
                            aria-hidden="true"
                        >
                            <i :class="cat.icon"></i>
                        </span>
                        <span class="reports-hub__head-text">
                            <span class="reports-hub__category-title">{{ cat.title }}</span>
                            <span class="reports-hub__category-desc">{{ cat.description }}</span>
                        </span>
                        <span class="reports-hub__badge">{{ cat.items.length }}</span>
                    </button>
                </h2>
                <div
                    :id="`collapse-${cat.slug}`"
                    class="accordion-collapse collapse"
                    :class="{ show: isFirstAndNoSearch(idx) || !!searchQuery }"
                    :data-bs-parent="searchQuery ? undefined : '#reportsHubAccordion'"
                    :aria-labelledby="`heading-${cat.slug}`"
                >
                    <div class="accordion-body reports-hub__body">
                        <ul class="reports-hub__list list-unstyled mb-0">
                            <li v-for="item in cat.items" :key="item.name">
                                <router-link
                                    class="reports-hub__link"
                                    :to="{ name: item.name }"
                                >
                                    <span
                                        v-if="searchQuery"
                                        v-html="highlight(item.label)"
                                    ></span>
                                    <template v-else>{{ item.label }}</template>
                                </router-link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="reports-hub__empty">
            <i class="ti ti-search-off"></i>
            <p>No reports match "<strong>{{ searchQuery }}</strong>"</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import { hasPermission } from '@/helpers/checkPermission';

const REPORT_CATEGORIES = [
    // 0 — Revenue (most checked daily)
    {
        title: 'Sales',
        slug: 'sales',
        description: 'Sales performance by period, item, category, customer, profit, tax, and discount.',
        icon: 'ti ti-shopping-cart',
        accentClass: 'is-teal',
        items: [
            { label: 'Sales Report', name: 'admin.sales-report', permission: 'list_sales_order' },
            { label: 'Sales By Item', name: 'admin.sales-by-item', permission: 'list_sales_order' },
            { label: 'Sales Summary', name: 'admin.sales-summary-report', permission: 'sales_summary_report' },
            { label: 'Daily Sales', name: 'admin.daily-sales-report', permission: 'daily_sales_report' },
            { label: 'Monthly Sales', name: 'admin.monthly-sales-report', permission: 'monthly_sales_report' },
            { label: 'Yearly Sales', name: 'admin.yearly-sales-report', permission: 'yearly_sales_report' },
            { label: 'Customer Wise Sales', name: 'admin.customer-wise-sales-report', permission: 'customer_wise_sales_report' },
            { label: 'Category Wise Sales', name: 'admin.category-wise-sales-report', permission: 'category_wise_sales_report' },
            { label: 'Sales Return', name: 'admin.sales-return-report', permission: 'sales_return_report' },
            { label: 'Outstanding Sales', name: 'admin.outstanding-sales-report', permission: 'outstanding_sales_report' },
            { label: 'Sales Tax', name: 'admin.sales-tax-report', permission: 'sales_tax_report' },
            { label: 'Sales Profit', name: 'admin.sales-profit-report', permission: 'sales_profit_report' },
            { label: 'Discount Report', name: 'admin.discount-report', permission: 'discount_report' },
            { label: 'Sales Ledger', name: 'admin.sales-ledger-report', permission: 'sales_ledger_report' },
        ],
    },
    // 2 — Cost control
    {
        title: 'Purchase',
        slug: 'purchase',
        description: 'Purchase reports by period, item, supplier, category, GRN, tax, and pending orders.',
        icon: 'ti ti-truck-delivery',
        accentClass: 'is-amber',
        items: [
            { label: 'Purchase Report', name: 'admin.purchase-report', permission: 'list_bill' },
            { label: 'Purchase By Item', name: 'admin.purchase-by-item', permission: 'list_bill' },
            { label: 'Purchase Summary', name: 'admin.purchase-summary-report', permission: 'purchase_summary_report' },
            { label: 'Daily Purchase', name: 'admin.daily-purchase-report', permission: 'daily_purchase_report' },
            { label: 'Monthly Purchase', name: 'admin.monthly-purchase-report', permission: 'monthly_purchase_report' },
            { label: 'Yearly Purchase', name: 'admin.yearly-purchase-report', permission: 'yearly_purchase_report' },
            { label: 'Supplier Wise Purchase', name: 'admin.supplier-wise-purchase-report', permission: 'supplier_wise_purchase_report' },
            { label: 'Category Wise Purchase', name: 'admin.category-wise-purchase-report', permission: 'category_wise_purchase_report' },
            { label: 'Purchase Return', name: 'admin.purchase-return-report', permission: 'purchase_return_report' },
            { label: 'Outstanding Purchase', name: 'admin.outstanding-purchase-report', permission: 'outstanding_purchase_report' },
            { label: 'Purchase Tax', name: 'admin.purchase-tax-report', permission: 'purchase_tax_report' },
            { label: 'Purchase Ledger', name: 'admin.purchase-ledger-report', permission: 'purchase_ledger_report' },
            { label: 'GRN Report', name: 'admin.grn-report', permission: 'grn_report' },
            { label: 'Pending Purchase', name: 'admin.pending-purchase-report', permission: 'pending_purchase_report' },
            { label: 'Purchase Discount', name: 'admin.purchase-discount-report', permission: 'purchase_discount_report' },
        ],
    },
    // 3 — Core financials
    {
        title: 'Accounting',
        slug: 'accounting',
        description: 'Core financial statements — trial balance, P&L, balance sheet, ledgers, and vouchers.',
        icon: 'ti ti-calculator',
        accentClass: 'is-blue',
        items: [
            { label: 'Trial Balance', name: 'admin.trial-balance', permission: 'list_account' },
            { label: 'Profit & Loss', name: 'admin.profit-and-loss', permission: 'list_account' },
            { label: 'Balance Sheet', name: 'admin.balance-sheet', permission: 'list_account' },
            { label: 'Cash Flow', name: 'admin.cash-flow', permission: 'list_account' },
            { label: 'Expense Statement', name: 'admin.expense-statement-report', permission: 'expense_statement_report' },
            { label: 'General Ledger', name: 'admin.general-ledger', permission: 'list_account' },
            { label: 'Cash Ledger', name: 'admin.cash-ledger-report', permission: 'list_account' },
            { label: 'Bank Ledger', name: 'admin.bank-ledger-report', permission: 'list_account' },
            { label: 'Customer Ledger', name: 'admin.sales-ledger-report', permission: 'sales_ledger_report' },
            { label: 'Supplier Ledger', name: 'admin.purchase-ledger-report', permission: 'purchase_ledger_report' },
            { label: 'Journal Report', name: 'admin.journal-report', permission: 'list_account' },
            { label: 'Payment Voucher Report', name: 'admin.payment-voucher-report', permission: 'list_account' },
            { label: 'Receipt Voucher Report', name: 'admin.receipt-voucher-report', permission: 'list_account' },
            { label: 'All Voucher Register', name: 'admin.all-voucher-register', permission: 'list_account' },
        ],
    },
    // 4 — Receivables
    {
        title: 'Customer',
        slug: 'customer',
        description: 'Customer balances, ledgers, outstanding amounts, ageing, and transaction history.',
        icon: 'ti ti-users',
        accentClass: 'is-green',
        items: [
            { label: 'Customer Ledger', name: 'admin.sales-ledger-report', permission: 'sales_ledger_report' },
            { label: 'Customer Outstanding / Ageing', name: 'admin.ar-aging', permission: 'list_account' },
            { label: 'Customer Transaction', name: 'admin.customer-transaction-report', permission: 'sales_ledger_report' },
            { label: 'Customer Statement', name: 'admin.customer-statement', permission: 'sales_ledger_report' },
            { label: 'Top Customers', name: 'admin.customer-wise-sales-report', permission: 'customer_wise_sales_report' },
        ],
    },
    // 5 — Payables
    {
        title: 'Supplier',
        slug: 'supplier',
        description: 'Supplier balances, ledgers, outstanding amounts, ageing, and transaction history.',
        icon: 'ti ti-building-store',
        accentClass: 'is-orange',
        items: [
            { label: 'Supplier Ledger', name: 'admin.purchase-ledger-report', permission: 'purchase_ledger_report' },
            { label: 'Supplier Outstanding / Ageing', name: 'admin.ap-aging', permission: 'list_account' },
            { label: 'Supplier Transaction', name: 'admin.supplier-transaction-report', permission: 'purchase_ledger_report' },
            { label: 'Supplier Statement', name: 'admin.supplier-statement', permission: 'purchase_ledger_report' },
            { label: 'Top Suppliers', name: 'admin.supplier-wise-purchase-report', permission: 'supplier_wise_purchase_report' },
        ],
    },
    // 6 — Liquidity
    {
        title: 'Cash & Bank',
        slug: 'cash-bank',
        description: 'Track cash and bank movements, reconcile statements, and monitor cheques.',
        icon: 'ti ti-cash',
        accentClass: 'is-teal',
        items: [
            { label: 'Cash Book', name: 'admin.cash-ledger-report', permission: 'list_account' },
            { label: 'Bank Book', name: 'admin.bank-ledger-report', permission: 'list_account' },
            { label: 'Bank Reconciliation', name: 'admin.bank-reconciliation', permission: 'list_account' },
            { label: 'Daily Collection', name: 'admin.daily-collection-report', permission: 'list_account' },
            { label: 'Daily Payment', name: 'admin.daily-payment-report', permission: 'list_account' },
            { label: 'Cheque Issue', name: 'admin.cheque-issue-report', permission: 'list_account' },
            { label: 'Cheque Receive', name: 'admin.cheque-receive-report', permission: 'list_account' },
        ],
    },
    // 7 — Stock
    {
        title: 'Inventory',
        slug: 'inventory',
        description: 'Stock levels, movement, valuation, aging, warehouse locations, expiry, and batch tracking.',
        icon: 'ti ti-packages',
        accentClass: 'is-cyan',
        items: [
            { label: 'Inventory Valuation', name: 'admin.inventory-valuation', permission: 'list_product' },
            { label: 'Stock Aging', name: 'admin.stock-aging', permission: 'list_product' },
            { label: 'Reorder Alerts', name: 'admin.reorder-alerts', permission: 'list_product' },
            { label: 'Stock Movement Report', name: 'admin.stock-movement-report', permission: 'list_product' },
            { label: 'Stock Ledger Report', name: 'admin.stock-ledger-report', permission: 'list_product' },
            { label: 'Warehouse Wise Stock', name: 'admin.warehouse-stock-report', permission: 'list_product' },
            { label: 'Warehouse Transfer Report', name: 'admin.warehouse-transfer-report', permission: 'list_product' },
            { label: 'Expiry Stock Report', name: 'admin.expiry-stock-report', permission: 'list_product' },
            { label: 'Dead Stock Report', name: 'admin.dead-stock-report', permission: 'list_product' },
            { label: 'Stock Opening Report', name: 'admin.stock-opening-report', permission: 'list_product' },
            { label: 'Inventory Summary Report', name: 'admin.inventory-summary-report', permission: 'inventory_summary_report' },
            { label: 'Damage Stock Report', name: 'admin.damage-stock-report', permission: 'list_damage_report' },
            { label: 'Production Variance Report', name: 'admin.production-variance-report', permission: 'list_production_order' },
            { label: 'Batch Stock Report', name: 'admin.batch-stock-report', permission: 'list_batch' },
            { label: 'Batch Traceability Report', name: 'admin.batch-traceability-report', permission: 'list_batch' },
        ],
    },
    // 8 — Compliance
    {
        title: 'Tax & Statutory',
        slug: 'tax-statutory',
        description: 'VAT returns, sales and purchase registers, TDS reports, and tax challans.',
        icon: 'ti ti-file-certificate',
        accentClass: 'is-mint',
        items: [
            { label: 'VAT Return (D3)', name: 'admin.vat-return', permission: 'list_account' },
            { label: 'Bikri Khata (Sales Register)', name: 'admin.vat-sales-register', permission: 'list_account' },
            { label: 'Kharid Khata (Purchase Register)', name: 'admin.vat-purchase-register', permission: 'list_account' },
            { label: 'TDS Report', name: 'admin.tds-report', permission: 'list_account' },
            { label: 'TDS Challan & Certificate', name: 'admin.tds-challan', permission: 'list_account' },
        ],
    },
    // 9 — People
    {
        title: 'HR & Payroll',
        slug: 'hr-payroll',
        description: 'Payroll summaries, attendance records, leave balances, and salary TDS.',
        icon: 'ti ti-users-group',
        accentClass: 'is-violet',
        items: [
            { label: 'Payroll Summary', name: 'admin.hr-report-payroll', permission: 'list_payroll' },
            { label: 'Attendance Report', name: 'admin.hr-report-attendance', permission: 'list_attendance' },
            { label: 'Leave Balance', name: 'admin.hr-report-leave', permission: 'list_leave_application' },
            { label: 'TDS Salary Report', name: 'admin.hr-report-tds-salary', permission: 'list_payroll' },
        ],
    },
    // 10 — System (least used)
    {
        title: 'System',
        slug: 'system',
        description: 'Integration health and sync status with government and external systems.',
        icon: 'ti ti-cloud-data-connection',
        accentClass: 'is-slate',
        items: [
            { label: 'IRD EBS Sync Status', name: 'admin.ird-sync', permission: 'list_account' },
        ],
    },
];

function canSeeItem(item) {
    if (!item.permission) return true;
    return hasPermission(item.permission);
}

const searchQuery = ref('');

const visibleCategories = computed(() =>
    REPORT_CATEGORIES.map((cat) => ({
        ...cat,
        items: cat.items.filter(canSeeItem),
    })).filter((cat) => cat.items.length > 0),
);

const filteredCategories = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return visibleCategories.value;
    return visibleCategories.value
        .map((cat) => ({
            ...cat,
            items: cat.items.filter((item) => item.label.toLowerCase().includes(q)),
        }))
        .filter((cat) => cat.items.length > 0);
});

const totalVisible = computed(() =>
    filteredCategories.value.reduce((n, cat) => n + cat.items.length, 0),
);

function isFirstAndNoSearch(idx) {
    return idx === 0 && !searchQuery.value;
}

function highlight(label) {
    const q = searchQuery.value.trim();
    if (!q) return label;
    const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return label.replace(
        new RegExp(escaped, 'gi'),
        (match) => `<mark class="reports-hub__mark">${match}</mark>`,
    );
}
</script>

<style scoped lang="scss">
/* ── Search bar ─────────────────────────────────────────── */
.reports-hub__search {
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;

    .search-input {
        position: relative;
        width: 320px;
        max-width: 100%;

        .btn-searchset {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            display: flex;
            align-items: center;
            color: #9595b5;
            pointer-events: none;
        }

        .form-control {
            padding-left: 2rem;
            padding-right: 2.25rem;
            border-radius: 8px;
            height: 38px;
            font-size: 14px;
        }

        .btn-clearsearch {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0 4px;
            line-height: 1;
            color: #9595b5;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 13px;

            &:hover {
                color: #475569;
            }
        }
    }
}

.reports-hub__search-meta {
    font-size: 0.875rem;
    color: #64748b;
    margin: 0;
}

/* ── Single-column grid ─────────────────────────────────── */
.reports-hub__grid {
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── Accordion card ─────────────────────────────────────── */
.reports-hub__category {
    border-radius: 0 !important;
    border-left: none !important;
    border-right: none !important;
    border-top: none !important;

    &:first-child {
        border-radius: 12px 12px 0 0 !important;
        border-top: 1px solid #e6e9ed !important;
        border-left: 1px solid #e6e9ed !important;
        border-right: 1px solid #e6e9ed !important;
    }

    &:last-child {
        border-radius: 0 0 12px 12px !important;
        border-left: 1px solid #e6e9ed !important;
        border-right: 1px solid #e6e9ed !important;
    }

    &:not(:first-child):not(:last-child) {
        border-left: 1px solid #e6e9ed !important;
        border-right: 1px solid #e6e9ed !important;
    }

    &:only-child {
        border-radius: 12px !important;
        border: 1px solid #e6e9ed !important;
    }
}

/* ── Toggle button ──────────────────────────────────────── */
.reports-hub__toggle {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    box-shadow: none !important;
    min-height: 68px;

    &:not(.collapsed) {
        color: #1e293b;
        background-color: #fff !important;
    }

    &.collapsed {
        color: #1e293b;
    }

    &::after {
        margin-left: auto;
        flex-shrink: 0;
    }
}

/* ── Category icon ──────────────────────────────────────── */
.reports-hub__icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
    color: #fff;

    &.is-blue   { background: linear-gradient(145deg, #2563eb, #1d4ed8); }
    &.is-green  { background: linear-gradient(145deg, #059669, #047857); }
    &.is-orange { background: linear-gradient(145deg, #ea580c, #c2410c); }
    &.is-teal   { background: linear-gradient(145deg, #0d9488, #0f766e); }
    &.is-amber  { background: linear-gradient(145deg, #d97706, #b45309); }
    &.is-cyan   { background: linear-gradient(145deg, #0891b2, #0e7490); }
    &.is-mint   { background: linear-gradient(145deg, #16a34a, #15803d); }
    &.is-violet { background: linear-gradient(145deg, #7c3aed, #6d28d9); }
    &.is-slate  { background: linear-gradient(145deg, #475569, #334155); }
}

/* ── Title + description block ──────────────────────────── */
.reports-hub__head-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
    text-align: left;
}

.reports-hub__category-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.01em;
    line-height: 1.3;
}

.reports-hub__category-desc {
    font-size: 0.8125rem;
    color: #94a3b8;
    font-weight: 400;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Count badge ────────────────────────────────────────── */
.reports-hub__badge {
    font-size: 0.72rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #64748b;
    border-radius: 20px;
    padding: 0.2em 0.65em;
    margin-right: 0.5rem;
    flex-shrink: 0;
}

/* ── Report links body ──────────────────────────────────── */
.reports-hub__body {
    padding: 0.75rem 1.25rem 1rem 1.25rem;
    border-top: 1px solid #f1f5f6;
    background: #fafbfe;
}

.reports-hub__list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 0;
}

.reports-hub__list li {
    width: 25%;

    @media (max-width: 1199.98px) {
        width: 33.333%;
    }

    @media (max-width: 767.98px) {
        width: 50%;
    }

    @media (max-width: 479.98px) {
        width: 100%;
    }
}

.reports-hub__link {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    color: #64748b;
    text-decoration: none;
    line-height: 1.45;
    padding: 0.3rem 0.5rem;
    border-radius: 4px;
    transition: color 0.13s ease, background 0.13s ease;

    &::before {
        content: '';
        display: inline-block;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #cbd5e1;
        margin-right: 0.55rem;
        flex-shrink: 0;
        transition: background 0.13s ease;
    }

    &:hover {
        color: #2563eb;
        background: #eff6ff;

        &::before {
            background: #2563eb;
        }
    }
}

/* ── Search highlight ───────────────────────────────────── */
:deep(.reports-hub__mark) {
    background: #fef08a;
    color: inherit;
    border-radius: 2px;
    padding: 0 1px;
}

/* ── Empty state ────────────────────────────────────────── */
.reports-hub__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    color: #94a3b8;
    text-align: center;

    .ti-search-off {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    p {
        font-size: 0.9375rem;
        margin: 0;
        color: #64748b;

        strong {
            color: #1e293b;
        }
    }
}
</style>
