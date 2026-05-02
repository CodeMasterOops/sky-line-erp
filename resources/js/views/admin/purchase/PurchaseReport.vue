<template>
    <PageHeader title="Purchase Report" subtitle="Purchase report" @refresh="generateReport"/>

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-currency-dollar fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Purchase</p>
                            <h4 class="mb-0">{{ formatAmount(dashboardSummary.total_amount) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-circle-check fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Paid</p>
                            <h4 class="mb-0">{{ formatAmount(dashboardSummary.total_paid) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-wallet fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Unpaid</p>
                            <h4 class="mb-0">{{ formatAmount(dashboardSummary.total_unpaid) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-alert-circle fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Overdue</p>
                            <h4 class="mb-0">{{ formatAmount(dashboardSummary.overdue_amount) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
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
                    <div class="col-xl-3 col-lg-3">
                        <VMultiselect
                            id="party_id"
                            v-model="filter.party_id"
                            :options="supplierOptions"
                            label="Supplier"
                            :disabled="isLoading"
                        />
                    </div>
                    <div class="col-xl-3 col-lg-4">
                        <VMultiselect
                            id="product_variant_id"
                            v-model="filter.product_variant_id"
                            :options="productVariantOptions"
                            label="Product"
                            :disabled="isLoading"
                        />
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="isLoading || !filter.from_date || !filter.to_date"
                            @click="generateReport"
                        >
                            {{ isLoading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card purchase-report-card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="purchase-report-header">
                    <div>
                        <h4 class="mb-1">Purchase Report</h4>
                        <p class="mb-0 text-primary">{{ reportPeriodLabel }}</p>
                    </div>
                    <div class="text-muted small">
                        Bills: {{ reportSummary.total_bills || 0 }}
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table purchase-report-table align-middle mb-0">
                        <thead>
                        <tr>
                            <th rowspan="2" class="text-center">SN</th>
                            <th colspan="5">Bill</th>
                            <th colspan="5">Item Detail</th>
                            <th colspan="2" class="text-end">Settlement</th>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <th>Bill No</th>
                            <th>Supplier</th>
                            <th>Due Date</th>
                            <th>Remarks</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Due</th>
                        </tr>
                        </thead>
                        <tbody>
                        <VLoader v-if="purchaseReport.loading" :colspan="13"/>
                        <template v-else-if="reportRows.length">
                            <template v-for="row in reportRows" :key="row.id">
                                <tr
                                    v-for="(item, itemIndex) in row.items"
                                    :key="`${row.id}-${item.id}`"
                                    :class="{'bill-group-start': itemIndex === 0}"
                                >
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count" class="text-center fw-semibold">
                                        {{ row.sn }}
                                    </td>
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count">{{ formatDate(row.bill_date) }}</td>
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count" class="fw-semibold">
                                        {{ row.bill_no }}
                                    </td>
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count">{{ row.party_name }}</td>
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count">{{ formatDate(row.due_date) }}</td>
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count">
                                        {{ row.remarks || '-' }}
                                    </td>
                                    <td>{{ item.product_variant_name }}</td>
                                    <td>{{ item.sku || '-' }}</td>
                                    <td class="text-end">{{ formatQuantity(item.quantity) }}</td>
                                    <td class="text-end">{{ formatAmount(item.rate) }}</td>
                                    <td class="text-end">{{ formatAmount(item.amount) }}</td>
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count" class="text-end fw-semibold">
                                        {{ formatAmount(row.paid_total) }}
                                    </td>
                                    <td v-if="itemIndex === 0" :rowspan="row.item_count" class="text-end fw-semibold">
                                        {{ formatAmount(row.due_amount) }}
                                    </td>
                                </tr>
                                <tr class="bill-summary-row">
                                    <td colspan="10" class="text-end fw-semibold">
                                        Bill Total
                                    </td>
                                    <td class="text-end fw-semibold">{{ formatAmount(row.grand_total) }}</td>
                                    <td class="text-end fw-semibold">{{ formatAmount(row.paid_total) }}</td>
                                    <td class="text-end fw-semibold">{{ formatAmount(row.due_amount) }}</td>
                                </tr>
                            </template>
                        </template>
                        <tr v-else>
                            <td colspan="13" class="text-center py-5 text-muted">
                                No purchase report data found for the selected filters.
                            </td>
                        </tr>
                        </tbody>
                        <tfoot v-if="!purchaseReport.loading">
                        <tr class="summary-row">
                            <th colspan="10" class="text-end">Grand Total</th>
                            <th class="text-end">{{ formatAmount(reportSummary.total_amount) }}</th>
                            <th class="text-end">{{ formatAmount(reportSummary.total_paid) }}</th>
                            <th class="text-end">{{ formatAmount(reportSummary.total_unpaid) }}</th>
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
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';
import {usePurchaseReportStore} from '@/stores/admin/purchase/report.js';

const dateRangeInput = ref(null);
const adminSettingStore = useAdminSettingStore();
const purchaseReportStore = usePurchaseReportStore();

const {fiscalYears} = storeToRefs(adminSettingStore);
const {dashboard, purchaseReport} = storeToRefs(purchaseReportStore);

const filter = reactive({
    from_date: '',
    to_date: '',
    party_id: '',
    product_variant_id: '',
});

let pickerInstance = null;

const isLoading = computed(() => dashboard.value.loading || purchaseReport.value.loading);

const dashboardSummary = computed(() => dashboard.value.data?.summary || {});
const reportSummary = computed(() => purchaseReport.value.data?.summary || {});
const reportRows = computed(() => purchaseReport.value.data?.rows || []);
const supplierOptions = computed(() => purchaseReport.value.data?.party_options || []);
const productVariantOptions = computed(() => purchaseReport.value.data?.product_variant_options || []);
const reportPeriodLabel = computed(() => purchaseReport.value.data?.period?.label || 'For the selected period');

const buildFilters = () => ({
    from_date: filter.from_date,
    to_date: filter.to_date,
    party_id: filter.party_id || undefined,
    product_variant_id: filter.product_variant_id || undefined,
});

const formatPickerValue = (startDate, endDate) => `${startDate.format('DD-MM-YYYY')} - ${endDate.format('DD-MM-YYYY')}`;

const applyDateRange = (startDate, endDate) => {
    filter.from_date = startDate.format('YYYY-MM-DD');
    filter.to_date = endDate.format('YYYY-MM-DD');

    if (dateRangeInput.value) {
        dateRangeInput.value.value = formatPickerValue(startDate, endDate);
    }
};

const syncPicker = () => {
    if (!pickerInstance || !filter.from_date || !filter.to_date) {
        return;
    }

    const startDate = moment(filter.from_date);
    const endDate = moment(filter.to_date);

    pickerInstance.setStartDate(startDate);
    pickerInstance.setEndDate(endDate);
    applyDateRange(startDate, endDate);
};

const initializePicker = () => {
    if (!dateRangeInput.value) {
        return;
    }

    const startDate = moment(filter.from_date || moment().startOf('month').format('YYYY-MM-DD'));
    const endDate = moment(filter.to_date || moment().format('YYYY-MM-DD'));

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

const formatQuantity = (value) => {
    const quantity = Number(value || 0);

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: Number.isInteger(quantity) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(quantity);
};

const formatDate = (value) => {
    return value ? moment(value).format('DD-MM-YYYY') : '-';
};

const generateReport = async () => {
    const payload = buildFilters();

    await Promise.all([
        purchaseReportStore.getDashboard(payload),
        purchaseReportStore.getPurchaseReport(payload),
    ]);
};

onMounted(async () => {
    await adminSettingStore.getFiscalYears();

    const currentFiscalYear = fiscalYears.value.data.find((item) => item.is_current) || fiscalYears.value.data[0];

    if (currentFiscalYear) {
        filter.from_date = currentFiscalYear.start_date;
        filter.to_date = currentFiscalYear.end_date;
    } else {
        filter.from_date = moment().startOf('month').format('YYYY-MM-DD');
        filter.to_date = moment().format('YYYY-MM-DD');
    }

    initializePicker();
    syncPicker();
    await generateReport();
});
</script>

<style scoped>
.sale-widget {
    overflow: hidden;
    position: relative;
}

.sale-widget::after {
    content: '';
    position: absolute;
    inset: auto -22px -22px auto;
    width: 92px;
    height: 92px;
    border-radius: 50%;
    opacity: 0.08;
    background: currentColor;
}

.sale-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.widget-success {
    color: #198754;
    background: linear-gradient(135deg, #ffffff 0%, #f1fbf5 100%);
}

.widget-info {
    color: #0d6efd;
    background: linear-gradient(135deg, #ffffff 0%, #eef6ff 100%);
}

.widget-warning {
    color: #b7791f;
    background: linear-gradient(135deg, #ffffff 0%, #fff9ed 100%);
}

.widget-danger {
    color: #dc3545;
    background: linear-gradient(135deg, #ffffff 0%, #fff1f3 100%);
}

.purchase-report-card {
    overflow: hidden;
}

.purchase-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.25rem 0.75rem;
}

.purchase-report-table {
    min-width: 1500px;
}

.purchase-report-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
    vertical-align: middle;
}

.purchase-report-table tbody td,
.purchase-report-table tfoot th {
    border-color: #e7edf5;
    vertical-align: middle;
}

.bill-group-start td {
    border-top-width: 2px;
    border-top-color: #cfd8e3;
}

.bill-summary-row td {
    background: #f8fafc;
}

.summary-row th {
    background: #eaf4ea;
    color: #24613f;
    font-weight: 700;
}

@media (max-width: 991.98px) {
    .purchase-report-header {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
