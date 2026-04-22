<template>
    <PageHeader title="General Ledger" subtitle="Accounting report" @refresh="generateReport" />

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
                        <label class="form-label">Account</label>
                        <select
                            v-model="filter.account_id"
                            class="form-select"
                            :disabled="generalLedger.loading"
                        >
                            <option value="">Select account</option>
                            <option
                                v-for="account in accountOptions"
                                :key="account.id"
                                :value="String(account.id)"
                            >
                                {{ account.label }}
                            </option>
                        </select>
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

        <div class="card general-ledger-card border-0">
            <div class="card-body p-0">
                <div class="general-ledger-header">
                    <div>
                        <h4 class="mb-1">General Ledger</h4>
                        <p class="mb-0 text-primary">{{ reportPeriodLabel }}</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table general-ledger-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="date-column">Date</th>
                                <th class="reference-column">Reference</th>
                                <th>Remarks</th>
                                <th class="amount-column text-end">Debit</th>
                                <th class="amount-column text-end">Credit</th>
                                <th class="amount-column text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <VLoader v-if="generalLedger.loading" :colspan="6" />
                            <template v-else-if="ledgerRows.length">
                                <tr v-for="(row, index) in ledgerRows" :key="`${row.type}-${index}`">
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
                                    Select an account to view ledger entries.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="!generalLedger.loading && generalLedger.data.summary && filter.account_id">
                            <tr class="summary-row">
                                <th colspan="3">Total</th>
                                <th class="text-end">{{ formatAmount(generalLedger.data.summary.total_dr) }}</th>
                                <th class="text-end">{{ formatAmount(generalLedger.data.summary.total_cr) }}</th>
                                <th class="text-end">{{ formatAmount(generalLedger.data.summary.closing_balance) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {storeToRefs} from 'pinia';
import {useAdminSettingStore} from '@/stores/admin/admin-setting.js';
import {useAccountingReportStore} from '@/stores/admin/accounting/report.js';

const adminSettingStore = useAdminSettingStore();
const accountingReportStore = useAccountingReportStore();

const {fiscalYears} = storeToRefs(adminSettingStore);
const {generalLedger} = storeToRefs(accountingReportStore);

const dateRangeInput = ref(null);

const filter = reactive({
    fiscal_year_id: '',
    start_date: '',
    end_date: '',
    account_id: '',
});

let pickerInstance = null;

const accountOptions = computed(() => generalLedger.value.data?.account_options || []);

const ledgerRows = computed(() => generalLedger.value.data?.rows || []);

const reportPeriodLabel = computed(() => {
    return generalLedger.value.data?.period?.label || 'For the selected period';
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

const formatAmount = (value) => {
    const amount = Number(value || 0);

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const generateReport = async () => {
    await accountingReportStore.getGeneralLedger({
        fiscal_year_id: filter.fiscal_year_id || undefined,
        start_date: filter.start_date,
        end_date: filter.end_date,
        account_id: filter.account_id || undefined,
    });
};

onMounted(async () => {
    await adminSettingStore.getFiscalYears();

    const currentFiscalYear = fiscalYears.value.data.find((item) => item.is_current) || fiscalYears.value.data[0];

    if (currentFiscalYear) {
        filter.fiscal_year_id = String(currentFiscalYear.id);
        filter.start_date = currentFiscalYear.start_date;
        filter.end_date = currentFiscalYear.end_date;
    } else {
        filter.start_date = moment().startOf('month').format('YYYY-MM-DD');
        filter.end_date = moment().format('YYYY-MM-DD');
    }

    initializePicker();
    syncPicker();
    await generateReport();
});
</script>

<style scoped>
.general-ledger-card {
    overflow: hidden;
}

.general-ledger-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.25rem 0.75rem;
}

.general-ledger-table {
    min-width: 920px;
}

.general-ledger-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}

.general-ledger-table tbody td,
.general-ledger-table tfoot th,
.general-ledger-table tfoot td {
    border-color: #e5edf6;
}

.date-column,
.reference-column {
    width: 180px;
}

.amount-column {
    width: 150px;
}

.summary-row th,
.summary-row td {
    background: #f3f7fc;
    font-weight: 700;
}
</style>
