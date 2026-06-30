<template>
    <PageHeader title="Expense Statement" subtitle="Breakdown of expense account balances for the period" @refresh="generateReport"/>

    <section class="section">
        <div class="card border-0 shadow-sm no-print">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-3 col-lg-5">
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
                        <VMultiselect
                            id="account_group_id"
                            v-model="filter.account_group_id"
                            label="Account Group"
                            :options="accountGroups.data"
                            :disabled="accountGroups.loading"
                            placeholder="All Expense Groups"
                        />
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="expenseStatement.loading || !filter.start_date || !filter.end_date"
                            @click="generateReport"
                        >
                            {{ expenseStatement.loading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ReportPrintShell
            v-if="dataLoaded"
            report-title="Expense Statement"
            :subtitle="reportPeriodLabel"
        >
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Account</th>
                        <th class="text-end">Period Dr</th>
                        <th class="text-end">Period Cr</th>
                        <th class="text-end">Net Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <VLoader v-if="expenseStatement.loading" :colspan="4"/>
                    <template v-else-if="expenseStatement.data.rows.length">
                        <template v-for="group in expenseStatement.data.rows" :key="group.id">
                            <tr class="table-light">
                                <td colspan="3" class="fw-semibold">{{ group.name }}</td>
                                <td class="text-end fw-semibold">{{ formatAmount(group.subtotal) }}</td>
                            </tr>
                            <tr v-for="account in group.accounts" :key="account.id">
                                <td class="ps-4">{{ account.name }}</td>
                                <td class="text-end">{{ formatAmount(account.period_dr) }}</td>
                                <td class="text-end">{{ formatAmount(account.period_cr) }}</td>
                                <td class="text-end">{{ formatAmount(account.net) }}</td>
                            </tr>
                            <template v-for="child in group.children" :key="child.id">
                                <tr class="table-secondary">
                                    <td colspan="3" class="ps-3 fw-semibold">{{ child.name }}</td>
                                    <td class="text-end fw-semibold">{{ formatAmount(child.subtotal) }}</td>
                                </tr>
                                <tr v-for="account in child.accounts" :key="account.id">
                                    <td class="ps-5">{{ account.name }}</td>
                                    <td class="text-end">{{ formatAmount(account.period_dr) }}</td>
                                    <td class="text-end">{{ formatAmount(account.period_cr) }}</td>
                                    <td class="text-end">{{ formatAmount(account.net) }}</td>
                                </tr>
                            </template>
                        </template>
                    </template>
                    <tr v-else>
                        <td colspan="4" class="text-center py-5 text-muted">No expense data found for the selected period.</td>
                    </tr>
                    </tbody>
                    <tfoot v-if="!expenseStatement.loading && expenseStatement.data.summary">
                    <tr class="table-dark">
                        <td colspan="3" class="fw-bold">Total Expenses</td>
                        <td class="text-end fw-bold">{{ formatAmount(expenseStatement.data.summary.total_expenses) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </ReportPrintShell>
        <div v-else class="text-center text-muted py-5 no-print">
            <i class="ti ti-chart-bar display-4 d-block mb-3"></i>
            Select a date range and click 'Generate' to load the expense statement.
        </div>
    </section>
</template>

<script setup>
import {computed, onMounted, reactive, ref, watch} from 'vue';
import moment from 'moment';
import {useRangePickerLabel} from '@/composables/useRangePickerLabel.js';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {storeToRefs} from 'pinia';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';
import {useAccountingReportStore} from '@/stores/admin/accounting/report.js';
import {useAccountGroupStore} from '@/stores/admin/accounting/account-group.js';
import {formatAmount} from '@/helpers/helper.js';
import ReportPrintShell from '@/components/print/ReportPrintShell.vue';

const adminSettingStore = useAdminSettingStore();
const accountingReportStore = useAccountingReportStore();
const accountGroupStore = useAccountGroupStore();

const {currentFiscalYear} = storeToRefs(adminSettingStore);
const {expenseStatement} = storeToRefs(accountingReportStore);
const {accountGroups} = storeToRefs(accountGroupStore);

const dateRangeInput = ref(null);
const dataLoaded = ref(false);

const filter = reactive({
    fiscal_year_id: '',
    account_group_id: '',
    start_date: '',
    end_date: '',
});

let pickerInstance = null;

const reportPeriodLabel = computed(() => expenseStatement.value.data?.period?.label || 'For the selected period');

const {mode: dateMode, formatPickerValue} = useRangePickerLabel();

const applyDateRange = (startDate, endDate) => {
    filter.start_date = startDate.format('YYYY-MM-DD');
    filter.end_date = endDate.format('YYYY-MM-DD');
    if (dateRangeInput.value) {
        dateRangeInput.value.value = formatPickerValue(startDate, endDate);
    }
};

const syncPicker = () => {
    if (!pickerInstance || !filter.start_date || !filter.end_date) { return; }
    const s = moment(filter.start_date);
    const e = moment(filter.end_date);
    pickerInstance.setStartDate(s);
    pickerInstance.setEndDate(e);
    applyDateRange(s, e);
};

watch(dateMode, () => syncPicker());

const initializePicker = () => {
    if (!dateRangeInput.value) { return; }
    const s = moment(filter.start_date || moment().startOf('month').format('YYYY-MM-DD'));
    const e = moment(filter.end_date || moment().format('YYYY-MM-DD'));
    pickerInstance = new DateRangePicker(dateRangeInput.value, {
        startDate: s, endDate: e, autoApply: true,
        locale: {format: 'DD-MM-YYYY'},
        ranges: {
            Today: [moment(), moment()],
            Yesterday: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        },
    }, applyDateRange);
    applyDateRange(s, e);
};

const generateReport = async () => {
    dataLoaded.value = true;
    await accountingReportStore.getExpenseStatement({
        fiscal_year_id: filter.fiscal_year_id || undefined,
        account_group_id: filter.account_group_id || undefined,
        start_date: filter.start_date,
        end_date: filter.end_date,
    });
};

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
};

onMounted(() => {
    accountGroupStore.getAccountGroups();
    setFilterDate();
    initializePicker();
    syncPicker();
});
</script>
