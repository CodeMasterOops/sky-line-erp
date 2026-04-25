<template>
    <PageHeader title="Journal Report" subtitle="Accounting report" @refresh="generateReport"/>

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
                    <div class="col-xl-3 col-lg-4">
                        <VMultiselect
                            id="journal_type"
                            v-model="filter.journal_type"
                            :options="journalTypes"
                            label="Journal Type"
                        />
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="journalReport.loading || !filter.start_date || !filter.end_date"
                            @click="generateReport"
                        >
                            {{ journalReport.loading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="dataLoaded" class="card journal-report-card border-0">
            <div class="card-body p-0">
                <div class="journal-report-header">
                    <div>
                        <h4 class="mb-1">Journal Report</h4>
                        <p class="mb-0 text-primary">{{ reportPeriodLabel }}</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table journal-report-table align-middle mb-0">
                        <thead>
                        <tr>
                            <th class="text-center sno-column">SNO.</th>
                            <th class="date-column">Date</th>
                            <th class="jno-column">JNO</th>
                            <th>Particular</th>
                            <th class="amount-column text-end">Dr(Rs.)</th>
                            <th class="amount-column text-end">Cr(Rs.)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <VLoader v-if="journalReport.loading" :colspan="6"/>
                        <template v-else-if="journalRows.length">
                            <template v-for="row in journalRows" :key="row.id">
                                <tr
                                    v-for="(item, itemIndex) in row.items"
                                    :key="`${row.id}-${item.id}`"
                                    :class="{'journal-group-start': itemIndex === 0}"
                                >
                                    <td v-if="itemIndex === 0" :rowspan="row.items.length"
                                        class="text-center fw-semibold">
                                        {{ row.sn }}
                                    </td>
                                    <td v-if="itemIndex === 0" :rowspan="row.items.length">
                                        <div>{{ row.date }}</div>
                                        <small v-if="row.type_label" class="text-muted">{{ row.type_label }}</small>
                                    </td>
                                    <td v-if="itemIndex === 0" :rowspan="row.items.length">
                                        <div>{{ row.voucher_no }}</div>
                                        <small v-if="row.reference_no" class="text-muted">{{ row.reference_no }}</small>
                                    </td>
                                    <td>
                                        {{ item.particular }}
                                    </td>
                                    <td class="text-end">{{ formatAmount(item.dr_amount) }}</td>
                                    <td class="text-end">{{ formatAmount(item.cr_amount) }}</td>
                                </tr>
                            </template>
                        </template>
                        <tr v-else>
                            <td colspan="6" class="text-center py-5 text-muted">
                                No journal report data found for the selected filters.
                            </td>
                        </tr>
                        </tbody>
                        <tfoot v-if="!journalReport.loading && journalReport.data.summary">
                        <tr class="summary-row">
                            <th colspan="4" class="text-end">Total</th>
                            <th class="text-end">{{ formatAmount(journalReport.data.summary.total_dr) }}</th>
                            <th class="text-end">{{ formatAmount(journalReport.data.summary.total_cr) }}</th>
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
import {useAdminSettingStore} from '@/stores/admin/admin-setting.js';
import {useAccountingReportStore} from '@/stores/admin/accounting/report.js';
import {formatAmount} from "@/helpers/helper.js";
import {useEnumStore} from "@/stores/admin/enum.js";

const adminSettingStore = useAdminSettingStore();
const accountingReportStore = useAccountingReportStore();
const enumStore = useEnumStore();

const {currentFiscalYear} = storeToRefs(adminSettingStore);
const {journalReport} = storeToRefs(accountingReportStore);
const {journalTypes} = storeToRefs(enumStore);

const dateRangeInput = ref(null);
const dataLoaded = ref(false);

const filter = reactive({
    fiscal_year_id: '',
    start_date: '',
    end_date: '',
    journal_type: '',
});

let pickerInstance = null;

const journalRows = computed(() => {
    return (journalReport.value.data?.rows || []).map((row) => ({
        ...row,
        items: row.items?.length ? row.items : [{
            id: `empty-${row.id}`,
            particular: '-',
            dr_amount: 0,
            cr_amount: 0,
        }],
    }));
});

const reportPeriodLabel = computed(() => {
    return journalReport.value.data?.period?.label || 'For the selected period';
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

const generateReport = async () => {
    dataLoaded.value = true;
    await accountingReportStore.getJournalReport({
        fiscal_year_id: filter.fiscal_year_id || undefined,
        start_date: filter.start_date,
        end_date: filter.end_date,
        journal_type: filter.journal_type || undefined,
    });
};

onMounted(() => {
    enumStore.getJournalTypes();
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

.journal-group-start td {
    border-top-width: 2px;
}

.sno-column {
    width: 72px;
}

.date-column,
.jno-column {
    width: 150px;
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
