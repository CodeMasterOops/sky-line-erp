<template>
    <PageHeader title="TDS Report" subtitle="Tax Deducted at Source – Nepal IRD" @refresh="generateReport" />

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
                    <div class="col-xl-2 col-lg-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="tdsReport.loading || !filter.start_date || !filter.end_date"
                            @click="generateReport"
                        >
                            {{ tdsReport.loading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                    <div class="col-xl-2 col-lg-4">
                        <button
                            type="button"
                            class="btn btn-outline-secondary w-100"
                            :disabled="!reportRows.length"
                            @click="exportCsv"
                        >
                            <i class="ti ti-file-export me-1"></i>
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="dataLoaded" class="card journal-report-card border-0">
            <div class="card-body p-0">
                <div class="journal-report-header">
                    <div>
                        <h4 class="mb-1">TDS Report</h4>
                        <p class="mb-0 text-primary">{{ reportPeriodLabel }}</p>
                    </div>
                </div>

                <div v-if="summary" class="row g-3 px-3 pb-3">
                    <div class="col-md-4">
                        <div class="card border-0 bg-primary-subtle mb-0">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Base Amount</h6>
                                <h4 class="fw-bold">NPR {{ fmt(summary.total_base_amount) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-danger-subtle mb-0">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total TDS Deducted</h6>
                                <h4 class="fw-bold text-danger">NPR {{ fmt(summary.total_tds_amount) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-success-subtle mb-0">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Net Amount Paid</h6>
                                <h4 class="fw-bold text-success">
                                    NPR {{ fmt((summary.total_base_amount || 0) - (summary.total_tds_amount || 0)) }}
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table journal-report-table align-middle mb-0">
                        <thead>
                        <tr>
                            <th class="text-center sno-column">SNO.</th>
                            <th>Party Name</th>
                            <th>PAN</th>
                            <th>TDS Category</th>
                            <th class="amount-column text-end">Base Amount</th>
                            <th class="amount-column text-end">Rate %</th>
                            <th class="amount-column text-end">TDS Amount</th>
                            <th>Period</th>
                        </tr>
                        </thead>
                        <tbody>
                        <VLoader v-if="tdsReport.loading" :colspan="8" />
                        <template v-else-if="reportRows.length">
                            <tr v-for="row in reportRows" :key="row.id">
                                <td class="text-center fw-semibold">{{ row.sn }}</td>
                                <td>{{ row.party_name || '-' }}</td>
                                <td>{{ row.party_pan || '-' }}</td>
                                <td>{{ row.tds_category }}</td>
                                <td class="text-end">{{ fmt(row.base_amount) }}</td>
                                <td class="text-end">{{ row.tds_rate }}%</td>
                                <td class="text-end text-danger fw-semibold">{{ fmt(row.tds_amount) }}</td>
                                <td>{{ row.period_month || '-' }}</td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="8" class="text-center py-5 text-muted">
                                No TDS deductions found for the selected filters.
                            </td>
                        </tr>
                        </tbody>
                        <tfoot v-if="!tdsReport.loading && summary">
                        <tr class="summary-row">
                            <th colspan="4" class="text-end">Total</th>
                            <th class="text-end">{{ fmt(summary.total_base_amount) }}</th>
                            <th></th>
                            <th class="text-end">{{ fmt(summary.total_tds_amount) }}</th>
                            <th></th>
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
import {computed, onMounted, reactive, ref} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {storeToRefs} from 'pinia';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';
import {useAccountingReportStore} from '@/stores/admin/accounting/report.js';

const adminSettingStore = useAdminSettingStore();
const accountingReportStore = useAccountingReportStore();

const {currentFiscalYear} = storeToRefs(adminSettingStore);
const {tdsReport} = storeToRefs(accountingReportStore);

const dateRangeInput = ref(null);
const dataLoaded = ref(false);

const filter = reactive({
    fiscal_year_id: '',
    start_date: '',
    end_date: '',
});

let pickerInstance = null;

const reportRows = computed(() =>
    (tdsReport.value.data?.rows || []).map((row, index) => ({
        ...row,
        id: row.id ?? `${row.party_id ?? 'row'}-${index}`,
        sn: index + 1,
    })),
);

const summary = computed(() => tdsReport.value.data?.summary || null);

const reportPeriodLabel = computed(() => {
    return tdsReport.value.data?.period?.label || 'For the selected period';
});

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', {minimumFractionDigits: 2});

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

const generateReport = async () => {
    dataLoaded.value = true;
    await accountingReportStore.getTdsReport({
        fiscal_year_id: filter.fiscal_year_id || undefined,
        start_date: filter.start_date,
        end_date: filter.end_date,
    });
};

const exportCsv = () => {
    if (!reportRows.value.length) return;

    const headers = ['Party Name', 'PAN', 'TDS Category', 'Base Amount', 'Rate %', 'TDS Amount', 'Period'];
    const csvRows = reportRows.value.map((row) => [
        row.party_name,
        row.party_pan,
        row.tds_category,
        row.base_amount,
        row.tds_rate,
        row.tds_amount,
        row.period_month,
    ].map((value) => `"${value ?? ''}"`).join(','));

    const csv = [headers.join(','), ...csvRows].join('\n');
    const blob = new Blob([csv], {type: 'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'tds-report.csv';
    a.click();
};

onMounted(async () => {
    await setFilterDate();
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
.journal-report-card {
    overflow: hidden;
}

.journal-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.25rem 0.75rem;
}

.journal-report-table {
    min-width: 980px;
}

.journal-report-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}

.journal-report-table tbody td,
.journal-report-table tfoot th,
.journal-report-table tfoot td {
    border-color: #e5edf6;
    vertical-align: top;
}

.sno-column {
    width: 72px;
}

.amount-column {
    width: 140px;
}

.summary-row th,
.summary-row td {
    background: #f3f7fc;
    font-weight: 700;
}
</style>
