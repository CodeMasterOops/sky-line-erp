<template>
    <PageHeader title="Bank Ledger" subtitle="Ledger for bank accounts" @refresh="generateReport"/>

    <section class="section">
        <div class="card border-0 shadow-sm no-print">
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
                        <VMultiselect
                            id="account_id"
                            v-model="filter.account_id"
                            :options="bankAccounts"
                            label="Bank Account"
                            :disabled="generalLedger.loading"
                        />
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="generalLedger.loading || !filter.start_date || !filter.end_date || !filter.account_id"
                            @click="generateReport"
                        >
                            {{ generalLedger.loading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ReportPrintShell
            v-if="dataLoaded"
            report-title="Bank Ledger"
            :subtitle="reportPeriodLabel"
            landscape
        >
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Remarks</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <VLoader v-if="generalLedger.loading" :colspan="6"/>
                    <template v-else-if="ledgerRows.length">
                        <tr v-for="(row, index) in ledgerRows" :key="index">
                            <td>{{ row.date || '' }}</td>
                            <td>{{ row.reference || '' }}</td>
                            <td>{{ row.remarks }}</td>
                            <td class="text-end">{{ formatAmount(row.debit) }}</td>
                            <td class="text-end">{{ formatAmount(row.credit) }}</td>
                            <td class="text-end">{{ formatAmount(row.balance) }}</td>
                        </tr>
                    </template>
                    <tr v-else>
                        <td colspan="6" class="text-center py-5 text-muted">
                            Select a bank account to view ledger entries.
                        </td>
                    </tr>
                    </tbody>
                    <tfoot v-if="!generalLedger.loading && generalLedger.data.summary && filter.account_id">
                    <tr class="table-light fw-semibold">
                        <td colspan="3">Total</td>
                        <td class="text-end">{{ formatAmount(generalLedger.data.summary.total_dr) }}</td>
                        <td class="text-end">{{ formatAmount(generalLedger.data.summary.total_cr) }}</td>
                        <td class="text-end">{{ formatAmount(generalLedger.data.summary.closing_balance) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </ReportPrintShell>
        <div v-else class="text-center text-muted py-5 no-print">
            <i class="ti ti-chart-bar display-4 d-block mb-3"></i>
            Select a bank account and click 'Generate' to load the ledger.
        </div>
    </section>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {storeToRefs} from 'pinia';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';
import {useAccountingReportStore} from '@/stores/admin/accounting/report.js';
import {formatAmount} from '@/helpers/helper.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import ReportPrintShell from '@/components/print/ReportPrintShell.vue';

const adminSettingStore = useAdminSettingStore();
const accountingReportStore = useAccountingReportStore();
const accountStore = useAccountStore();

const {currentFiscalYear} = storeToRefs(adminSettingStore);
const {generalLedger} = storeToRefs(accountingReportStore);
const {accounts} = storeToRefs(accountStore);

const dateRangeInput = ref(null);
const dataLoaded = ref(false);

const filter = reactive({
    fiscal_year_id: '',
    start_date: '',
    end_date: '',
    account_id: '',
});

let pickerInstance = null;

const bankAccounts = computed(() =>
    (accounts.value?.data || []).filter((a) => a.label?.toLowerCase().includes('bank'))
);

const ledgerRows = computed(() => generalLedger.value.data?.rows || []);
const reportPeriodLabel = computed(() => generalLedger.value.data?.period?.label || 'For the selected period');

const applyDateRange = (startDate, endDate) => {
    filter.start_date = startDate.format('YYYY-MM-DD');
    filter.end_date = endDate.format('YYYY-MM-DD');
    if (dateRangeInput.value) {
        dateRangeInput.value.value = `${startDate.format('DD-MM-YYYY')} - ${endDate.format('DD-MM-YYYY')}`;
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
    await accountingReportStore.getGeneralLedger({
        fiscal_year_id: filter.fiscal_year_id || '',
        start_date: filter.start_date,
        end_date: filter.end_date,
        account_id: filter.account_id || '',
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
    accountStore.getAccounts();
    setFilterDate();
    initializePicker();
    syncPicker();
});
</script>
