<template>
    <PageHeader title="Profit and Loss" subtitle="Accounting report" @refresh="generateReport"/>

    <section class="section">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-4 col-lg-5">
                        <label class="form-label">Date Range</label>
                        <div class="input-icon-start position-relative">
                            <input
                                ref="dateRangeInput"
                                type="text"
                                class="form-control"
                                placeholder="dd-mm-yyyy - dd-mm-yyyy"
                            >
                            <span class="input-icon-left">
                                <i class="ti ti-calendar"></i>
                            </span>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4">
                        <label class="form-label">Compare Fiscal Year</label>
                        <select
                            v-model="filter.compare_fiscal_year_id"
                            class="form-select"
                            :disabled="fiscalYears.loading"
                        >
                            <option value="">None</option>
                            <option
                                v-for="fy in compareFiscalYearOptions"
                                :key="fy.id"
                                :value="String(fy.id)"
                            >
                                {{ fy.year_name }}{{ fy.is_current ? ' (Current)' : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="profitLoss.loading || !filter.start_date || !filter.end_date"
                            @click="generateReport"
                        >
                            {{ profitLoss.loading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="dataLoaded" class="card profit-loss-card border-0">
            <div class="card-body p-0">
                <div class="profit-loss-header">
                    <div>
                        <h4 class="mb-1">Profit and Loss</h4>
                        <p class="mb-0 text-primary">{{ reportPeriodLabel }}</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table profit-loss-table align-middle mb-0">
                        <thead>
                        <tr>
                            <th class="account-column">Accounts</th>
                            <th v-if="hasCompareFiscalYear" class="text-end">Amount (Prev Fiscal Year)</th>
                            <th class="text-end">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <VLoader v-if="profitLoss.loading" :colspan="hasCompareFiscalYear ? 3 : 2"/>
                        <template v-else-if="flatRows.length">
                            <tr
                                v-for="row in flatRows"
                                :key="row.key"
                                :class="[
                                        row.type === 'group' ? 'group-row' : 'account-row',
                                        `level-${row.level}`,
                                    ]"
                            >
                                <td class="account-column">
                                    <div
                                        class="account-cell"
                                        :style="{ paddingLeft: `${row.level * 20 + 12}px` }"
                                    >
                                        <button
                                            v-if="row.type === 'group' && row.children.length"
                                            type="button"
                                            class="toggle-button"
                                            @click="toggleRow(row.key)"
                                        >
                                            <i
                                                class="ti"
                                                :class="isExpanded(row.key) ? 'ti-chevron-down' : 'ti-chevron-right'"
                                            ></i>
                                        </button>
                                        <span v-else class="toggle-placeholder"></span>
                                        <span class="account-label">{{ row.label }}</span>
                                    </div>
                                </td>
                                <td v-if="hasCompareFiscalYear" class="text-end">
                                    {{ formatSignedAmount(row.prev_amount) }}
                                </td>
                                <td class="text-end">{{ formatSignedAmount(row.amount) }}</td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td :colspan="hasCompareFiscalYear ? 3 : 2" class="text-center py-5 text-muted">
                                No report data found for the selected period.
                            </td>
                        </tr>
                        </tbody>
                        <tfoot v-if="!profitLoss.loading && profitLoss.data.summary">
                        <tr class="summary-row">
                            <th>Total Income</th>
                            <th v-if="hasCompareFiscalYear" class="text-end">
                                {{ formatSignedAmount(profitLoss.data.summary.prev_income) }}
                            </th>
                            <th class="text-end">{{ formatSignedAmount(profitLoss.data.summary.income) }}</th>
                        </tr>
                        <tr class="summary-row">
                            <th>Total Expense</th>
                            <th v-if="hasCompareFiscalYear" class="text-end">
                                {{ formatSignedAmount(profitLoss.data.summary.prev_expense) }}
                            </th>
                            <th class="text-end">{{ formatSignedAmount(profitLoss.data.summary.expense) }}</th>
                        </tr>
                        <tr class="summary-row net-row">
                            <th>Net Profit</th>
                            <th v-if="hasCompareFiscalYear" class="text-end">
                                {{ formatSignedAmount(profitLoss.data.summary.prev_net_profit) }}
                            </th>
                            <th class="text-end">{{ formatSignedAmount(profitLoss.data.summary.net_profit) }}</th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div v-else class="text-center text-muted py-5">
            <i class="ti ti-chart-bar display-4 d-block mb-3"></i>
            Select a report filter from the top panel and click 'Generate' to load the report.
        </div>
    </section>
</template>

<script setup>
import {computed, onMounted, reactive, ref, watch} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {storeToRefs} from 'pinia';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';
import {useAccountingReportStore} from '@/stores/admin/accounting/report.js';
import {formatAmount} from "@/helpers/helper.js";

const adminSettingStore = useAdminSettingStore();
const accountingReportStore = useAccountingReportStore();

const {fiscalYears, currentFiscalYear} = storeToRefs(adminSettingStore);
const {profitLoss} = storeToRefs(accountingReportStore);

const dateRangeInput = ref(null);
const expandedRows = ref(new Set());
const dataLoaded = ref(false);

const filter = reactive({
    fiscal_year_id: '',
    compare_fiscal_year_id: '',
    start_date: '',
    end_date: '',
});

let pickerInstance = null;

const compareFiscalYearOptions = computed(() => {
    return fiscalYears.value.data.filter((fy) => String(fy.id) !== String(filter.fiscal_year_id));
});

const hasCompareFiscalYear = computed(() => Boolean(profitLoss.value.data?.compare_fiscal_year));

const reportPeriodLabel = computed(() => {
    return profitLoss.value.data?.period?.label || 'For the selected period';
});

const formatPickerValue = (startDate, endDate) => `${startDate.format('DD-MM-YYYY')} - ${endDate.format('DD-MM-YYYY')}`;

const applyDateRange = (startDate, endDate) => {
    filter.start_date = startDate.format('YYYY-MM-DD');
    filter.end_date = endDate.format('YYYY-MM-DD');

    if (dateRangeInput.value) {
        dateRangeInput.value.value = formatPickerValue(startDate, endDate);
    }
};

const syncPicker = () => {
    if (!pickerInstance || !filter.start_date || !filter.end_date) {
        return;
    }

    const startDate = moment(filter.start_date);
    const endDate = moment(filter.end_date);

    pickerInstance.setStartDate(startDate);
    pickerInstance.setEndDate(endDate);
    applyDateRange(startDate, endDate);
};

const initializePicker = () => {
    if (!dateRangeInput.value) {
        return;
    }

    const startDate = moment(filter.start_date || moment().startOf('month').format('YYYY-MM-DD'));
    const endDate = moment(filter.end_date || moment().format('YYYY-MM-DD'));

    pickerInstance = new DateRangePicker(
        dateRangeInput.value,
        {
            startDate,
            endDate,
            autoApply: true,
            locale: {
                format: 'DD-MM-YYYY',
            },
            ranges: {
                Today: [moment(), moment()],
                Yesterday: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [
                    moment().subtract(1, 'month').startOf('month'),
                    moment().subtract(1, 'month').endOf('month'),
                ],
            },
        },
        applyDateRange
    );

    applyDateRange(startDate, endDate);
};

const collectExpandedKeys = (rows = []) => {
    const keys = [];

    rows.forEach((row) => {
        if (row.type === 'group' && row.children.length) {
            keys.push(row.key);
            keys.push(...collectExpandedKeys(row.children));
        }
    });

    return keys;
};

const flattenRows = (rows = [], level = 0) => {
    const items = [];

    rows.forEach((row) => {
        items.push({
            ...row,
            level,
        });

        if (row.type === 'group' && row.children.length && expandedRows.value.has(row.key)) {
            items.push(...flattenRows(row.children, level + 1));
        }
    });

    return items;
};

const toggleRow = (key) => {
    const next = new Set(expandedRows.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expandedRows.value = next;
};

const isExpanded = (key) => expandedRows.value.has(key);

const flatRows = computed(() => flattenRows(profitLoss.value.data?.rows || []));

const formatSignedAmount = (value) => {
    const formatted = formatAmount(value);

    return value < 0 ? `(${Math.abs(formatted)})` : formatted;
};

const generateReport = async () => {
    dataLoaded.value = true;
    await accountingReportStore.getProfitLoss({
        fiscal_year_id: filter.fiscal_year_id || '',
        compare_fiscal_year_id: filter.compare_fiscal_year_id || '',
        start_date: filter.start_date,
        end_date: filter.end_date,
    });
};

watch(
    () => profitLoss.value.data?.rows,
    (rows) => {
        expandedRows.value = new Set(collectExpandedKeys(rows || []));
    },
    {deep: true}
);

onMounted(() => {
    adminSettingStore.getFiscalYears();
    setFilterDate();
    initializePicker();
    syncPicker();
});

const setFilterDate = async () => {
    await adminSettingStore.getCurrentFiscalYear();

    if (currentFiscalYear.value.data.start_date) {
        filter.fiscal_year_id = currentFiscalYear.value.data.id;
        filter.start_date = currentFiscalYear.value.data.start_date;
        filter.end_date = currentFiscalYear.value.data.end_date;
    } else {
        filter.start_date = moment().startOf('month').format('YYYY-MM-DD');
        filter.end_date = moment().format('YYYY-MM-DD');
    }
}
</script>

<style scoped>
.profit-loss-card {
    overflow: hidden;
}

.profit-loss-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.25rem 0.75rem;
}

.profit-loss-table {
    min-width: 860px;
}

.profit-loss-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}

.profit-loss-table tbody td,
.profit-loss-table tfoot th,
.profit-loss-table tfoot td {
    border-color: #e5edf6;
}

.account-column {
    min-width: 480px;
}

.account-cell {
    display: flex;
    align-items: center;
    min-height: 36px;
}

.toggle-button {
    width: 24px;
    height: 24px;
    border: 0;
    background: transparent;
    color: #4f5f79;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin-right: 6px;
}

.toggle-placeholder {
    display: inline-block;
    width: 24px;
    margin-right: 6px;
}

.account-label {
    color: #425466;
    font-weight: 600;
}

.group-row td {
    background: #f7faff;
    font-weight: 700;
}

.group-row.level-0 td {
    background: #edf3fa;
}

.account-row td {
    background: #fff;
}

.account-row .account-label {
    font-weight: 500;
}

.summary-row th,
.summary-row td {
    background: #f3f7fc;
    font-weight: 700;
}

.net-row th,
.net-row td {
    background: #eaf4ee;
}
</style>
