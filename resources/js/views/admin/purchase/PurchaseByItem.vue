<template>
    <PageHeader title="Purchase By Item" subtitle="Purchase report" @refresh="generateReport"/>

    <section class="section">
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
                    <div class="col-xl-4 col-lg-4">
                        <VMultiselect
                            id="product_variant_id"
                            v-model="filter.product_variant_id"
                            :options="productVariantOptions"
                            label="Product"
                            :disabled="purchaseByItem.loading"
                        />
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="purchaseByItem.loading || !filter.from_date || !filter.to_date"
                            @click="generateReport"
                        >
                            {{ purchaseByItem.loading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card purchase-by-item-card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="purchase-by-item-header">
                    <div>
                        <h4 class="mb-1">Purchase By Item</h4>
                        <p class="mb-0 text-primary">
                            For the period {{ reportPeriodLabel }}
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table purchase-by-item-table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">Net Purchase</th>
                            <th class="text-end">Vat Amount</th>
                            <th class="text-end">Total Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <VLoader v-if="purchaseByItem.loading" :colspan="7"/>
                        <template v-else-if="reportRows.length">
                            <tr v-for="row in reportRows" :key="row.id">
                                <td class="product-cell">{{ row.product_name }}</td>
                                <td class="text-end">{{ formatQuantity(row.quantity) }}</td>
                                <td class="text-end">{{ formatAmount(row.amount) }}</td>
                                <td class="text-end">{{ formatAmount(row.discount, true) }}</td>
                                <td class="text-end">{{ formatAmount(row.net_sales) }}</td>
                                <td class="text-end">{{ formatAmount(row.vat_amount, true) }}</td>
                                <td class="text-end">{{ formatAmount(row.total_amount) }}</td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="7" class="text-center py-5 text-muted">
                                No purchase-by-item data found for the selected filters.
                            </td>
                        </tr>
                        </tbody>
                        <tfoot v-if="!purchaseByItem.loading">
                        <tr class="summary-row">
                            <th class="text-end">Total</th>
                            <th class="text-end">{{ formatQuantity(summary.quantity) }}</th>
                            <th class="text-end">{{ formatAmount(summary.amount) }}</th>
                            <th class="text-end">{{ formatAmount(summary.discount, true) }}</th>
                            <th class="text-end">{{ formatAmount(summary.net_sales) }}</th>
                            <th class="text-end">{{ formatAmount(summary.vat_amount, true) }}</th>
                            <th class="text-end">{{ formatAmount(summary.total_amount) }}</th>
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
import {usePurchaseReportStore} from '@/stores/admin/purchase/report.js';
import {formatAmount} from "@/helpers/helper.js";

const adminSettingStore = useAdminSettingStore();
const purchaseReportStore = usePurchaseReportStore();

const {fiscalYears} = storeToRefs(adminSettingStore);
const {purchaseByItem} = storeToRefs(purchaseReportStore);

const dateRangeInput = ref(null);

const filter = reactive({
    from_date: '',
    to_date: '',
    product_variant_id: '',
});

let pickerInstance = null;

const reportRows = computed(() => purchaseByItem.value.data?.rows || []);
const productVariantOptions = computed(() => purchaseByItem.value.data?.product_variant_options || []);
const summary = computed(() => purchaseByItem.value.data?.summary || {});
const reportPeriodLabel = computed(() => {
    const period = purchaseByItem.value.data?.period;

    if (!period?.from_date || !period?.to_date) {
        return 'the selected period';
    }

    return `${moment(period.from_date).format('DD-MM-YYYY')} to ${moment(period.to_date).format('DD-MM-YYYY')}`;
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

const formatQuantity = (value) => {
    const quantity = Number(value || 0);

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: Number.isInteger(quantity) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(quantity);
};

const generateReport = async () => {
    await purchaseReportStore.getPurchaseByItem({
        from_date: filter.from_date,
        to_date: filter.to_date,
        product_variant_id: filter.product_variant_id || undefined,
    });
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
.purchase-by-item-card {
    overflow: hidden;
}

.purchase-by-item-header {
    padding: 1.25rem 1.25rem 0.75rem;
}

.purchase-by-item-table {
    min-width: 1100px;
}

.purchase-by-item-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}

.purchase-by-item-table tbody td,
.purchase-by-item-table tfoot th {
    border-color: #e7edf5;
}

.product-cell {
    color: #1d4ed8;
    font-weight: 500;
}

.summary-row th {
    background: #f8fafc;
    color: #243449;
    font-weight: 700;
}
</style>
