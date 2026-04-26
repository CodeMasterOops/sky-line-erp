<template>
    <div class="reports-hub">
        <PageHeader
            title="Reports"
            subtitle="Open a report by category. Links respect your role permissions."
            :hide-action-buttons="true"
        />

        <div class="reports-hub__grid">
            <section
                v-for="cat in visibleCategories"
                :key="cat.title"
                class="reports-hub__category"
            >
                <div class="reports-hub__category-head">
                    <span
                        class="reports-hub__icon"
                        :class="cat.accentClass"
                        aria-hidden="true"
                    >
                        <i :class="cat.icon"></i>
                    </span>
                    <h2 class="reports-hub__category-title">{{ cat.title }}</h2>
                </div>
                <ul class="reports-hub__list list-unstyled mb-0">
                    <li v-for="item in cat.items" :key="item.name">
                        <router-link
                            class="reports-hub__link"
                            :to="{ name: item.name }"
                        >
                            {{ item.label }}
                        </router-link>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import { hasPermission } from '@/helpers/checkPermission';

const REPORT_CATEGORIES = [
    {
        title: 'Accounting',
        icon: 'ti ti-calculator',
        accentClass: 'is-blue',
        items: [
            { label: 'Trial Balance', name: 'admin.trial-balance', permission: 'list_account' },
            { label: 'Profit & Loss', name: 'admin.profit-and-loss', permission: 'list_account' },
            { label: 'Balance Sheet', name: 'admin.balance-sheet', permission: 'list_account' },
            { label: 'Cash Flow', name: 'admin.cash-flow', permission: 'list_account' },
            { label: 'General Ledger', name: 'admin.general-ledger', permission: 'list_account' },
            { label: 'Journal Report', name: 'admin.journal-report', permission: 'list_account' },
        ],
    },
    {
        title: 'Receivable',
        icon: 'ti ti-cash',
        accentClass: 'is-green',
        items: [
            { label: 'AR Aging (customer ageing)', name: 'admin.ar-aging', permission: 'list_account' },
        ],
    },
    {
        title: 'Payable',
        icon: 'ti ti-building-bank',
        accentClass: 'is-orange',
        items: [
            { label: 'AP Aging (supplier ageing)', name: 'admin.ap-aging', permission: 'list_account' },
        ],
    },
    {
        title: 'Sales',
        icon: 'ti ti-shopping-cart',
        accentClass: 'is-teal',
        items: [
            { label: 'Sales Report', name: 'admin.sales-report', permission: 'list_sales_order' },
        ],
    },
    {
        title: 'Purchase',
        icon: 'ti ti-truck-delivery',
        accentClass: 'is-amber',
        items: [
            { label: 'Purchase Report', name: 'admin.purchase-report', permission: 'list_bill' },
        ],
    },
    {
        title: 'Inventory',
        icon: 'ti ti-packages',
        accentClass: 'is-cyan',
        items: [
            { label: 'Inventory Valuation', name: 'admin.inventory-valuation', permission: 'list_product' },
            { label: 'Stock Aging', name: 'admin.stock-aging', permission: 'list_product' },
            { label: 'Reorder Alerts', name: 'admin.reorder-alerts', permission: 'list_product' },
        ],
    },
    {
        title: 'Tax & statutory',
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
    {
        title: 'HR & payroll',
        icon: 'ti ti-users-group',
        accentClass: 'is-violet',
        items: [
            { label: 'Payroll Summary', name: 'admin.hr-report-payroll', permission: 'list_payroll' },
            { label: 'Attendance Report', name: 'admin.hr-report-attendance', permission: 'list_attendance' },
            { label: 'Leave Balance', name: 'admin.hr-report-leave', permission: 'list_leave_application' },
            { label: 'TDS Salary Report', name: 'admin.hr-report-tds-salary', permission: 'list_payroll' },
        ],
    },
    {
        title: 'System',
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

const visibleCategories = computed(() =>
    REPORT_CATEGORIES.map((cat) => ({
        ...cat,
        items: cat.items.filter(canSeeItem),
    })).filter((cat) => cat.items.length > 0),
);
</script>

<style scoped lang="scss">
.reports-hub__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 2rem 2.5rem;
    margin-top: 0.5rem;
}

@media (max-width: 1199.98px) {
    .reports-hub__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .reports-hub__grid {
        grid-template-columns: 1fr;
        gap: 1.75rem;
    }
}

.reports-hub__category {
    min-width: 0;
}

.reports-hub__category-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.reports-hub__icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.15rem;
    color: #fff;

    &.is-blue {
        background: linear-gradient(145deg, #2563eb, #1d4ed8);
    }
    &.is-green {
        background: linear-gradient(145deg, #059669, #047857);
    }
    &.is-orange {
        background: linear-gradient(145deg, #ea580c, #c2410c);
    }
    &.is-teal {
        background: linear-gradient(145deg, #0d9488, #0f766e);
    }
    &.is-amber {
        background: linear-gradient(145deg, #d97706, #b45309);
    }
    &.is-cyan {
        background: linear-gradient(145deg, #0891b2, #0e7490);
    }
    &.is-mint {
        background: linear-gradient(145deg, #16a34a, #15803d);
    }
    &.is-violet {
        background: linear-gradient(145deg, #7c3aed, #6d28d9);
    }
    &.is-slate {
        background: linear-gradient(145deg, #475569, #334155);
    }
}

.reports-hub__category-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    color: #1e293b;
    letter-spacing: -0.01em;
}

.reports-hub__list li + li {
    margin-top: 0.35rem;
}

.reports-hub__link {
    display: inline-block;
    font-size: 0.9375rem;
    color: #64748b;
    text-decoration: none;
    line-height: 1.45;
    transition: color 0.15s ease;

    &:hover {
        color: #2563eb;
    }
}
</style>
