<template>
    <PageHeader title="All Voucher Register" subtitle="Complete register of all vouchers and journal entries" @refresh="generateReport"/>

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
                    <div class="col-xl-3 col-lg-4">
                        <VMultiselect
                            id="journal_type"
                            v-model="filter.journal_type"
                            :options="journalTypes"
                            label="Voucher Type"
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

        <ReportPrintShell
            v-if="dataLoaded"
            report-title="All Voucher Register"
            :subtitle="reportPeriodLabel"
            landscape
        >
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th class="text-center">SNO.</th>
                        <th>Date</th>
                        <th>JNO</th>
                        <th>Type</th>
                        <th>Particular</th>
                        <th class="text-end">Dr (Rs.)</th>
                        <th class="text-end">Cr (Rs.)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <VLoader v-if="journalReport.loading" :colspan="7"/>
                    <template v-else-if="journalRows.length">
                        <template v-for="row in journalRows" :key="row.id">
                            <tr
                                v-for="(item, itemIndex) in row.items"
                                :key="`${row.id}-${item.id}`"
                            >
                                <td v-if="itemIndex === 0" :rowspan="row.items.length" class="text-center fw-semibold">
                                    {{ row.sn }}
                                </td>
                                <td v-if="itemIndex === 0" :rowspan="row.items.length">
                                    <div>{{ adToBsDate(row.date) }}</div>
                                </td>
                                <td v-if="itemIndex === 0" :rowspan="row.items.length">
                                    <div>{{ row.voucher_no }}</div>
                                    <small v-if="row.reference_no" class="text-muted">{{ row.reference_no }}</small>
                                </td>
                                <td v-if="itemIndex === 0" :rowspan="row.items.length">
                                    <span v-if="row.type_label" class="badge bg-secondary-subtle text-secondary">{{ row.type_label }}</span>
                                </td>
                                <td>{{ item.particular }}</td>
                                <td class="text-end">{{ formatAmount(item.dr_amount) }}</td>
                                <td class="text-end">{{ formatAmount(item.cr_amount) }}</td>
                            </tr>
                        </template>
                    </template>
                    <tr v-else>
                        <td colspan="7" class="text-center py-5 text-muted">No vouchers found for the selected filters.</td>
                    </tr>
                    </tbody>
                    <tfoot v-if="!journalReport.loading && journalReport.data.summary">
                    <tr class="table-light fw-semibold">
                        <td colspan="5" class="text-end">Total</td>
                        <td class="text-end">{{ formatAmount(journalReport.data.summary.total_dr) }}</td>
                        <td class="text-end">{{ formatAmount(journalReport.data.summary.total_cr) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </ReportPrintShell>
        <div v-else class="text-center text-muted py-5 no-print">
            <i class="ti ti-chart-bar display-4 d-block mb-3"></i>
            Select a date range and click 'Generate' to load the voucher register.
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
import {adToBsDate, formatAmount} from '@/helpers/helper.js';
import {useEnumStore} from '@/stores/admin/enum.js';
import ReportPrintShell from '@/components/print/ReportPrintShell.vue';

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

const journalRows = computed(() =>
    (journalReport.value.data?.rows || []).map((row) => ({
        ...row,
        items: row.items?.length ? row.items : [{id: `empty-${row.id}`, particular: '-', dr_amount: 0, cr_amount: 0}],
    }))
);

const reportPeriodLabel = computed(() => journalReport.value.data?.period?.label || 'For the selected period');

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
    await accountingReportStore.getJournalReport({
        fiscal_year_id: filter.fiscal_year_id || undefined,
        start_date: filter.start_date,
        end_date: filter.end_date,
        journal_type: filter.journal_type || undefined,
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
    enumStore.getJournalTypes();
    setFilterDate();
    initializePicker();
    syncPicker();
});
</script>
