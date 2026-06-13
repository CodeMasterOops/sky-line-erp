<template>
    <PageHeader hide-action-buttons title="VAT Register" subtitle="D1 / D2 Sales VAT Register" @refresh="generateReport" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-4 col-md-6">
                        <label class="form-label">Date Range</label>
                        <div class="input-icon-start position-relative">
                            <input
                                ref="dateRangeInput"
                                type="text"
                                class="form-control"
                                placeholder="dd-mm-yyyy - dd-mm-yyyy"
                            >
                            <span class="input-icon-left"><i class="ti ti-calendar"></i></span>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="vatRegister.loading"
                            @click="generateReport"
                        >
                            {{ vatRegister.loading ? 'Loading...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Taxable Sales</p>
                        <h5 class="mb-0 text-primary">{{ formatAmount(vatRegister.data.totals.taxable) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">VAT (13%)</p>
                        <h5 class="mb-0 text-danger">{{ formatAmount(vatRegister.data.totals.vat) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Exempt Sales</p>
                        <h5 class="mb-0 text-secondary">{{ formatAmount(vatRegister.data.totals.exempt) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Zero Rated</p>
                        <h5 class="mb-0 text-info">{{ formatAmount(vatRegister.data.totals.zero_rated) }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Taxable Sales Table -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-0">
                <div class="vat-table-header px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-primary">D1 — Taxable Sales</h6>
                    <span class="badge bg-primary-subtle text-primary">{{ vatRegister.data.taxable_sales.length }} invoices</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 vat-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>PAN</th>
                                <th class="text-end">Taxable Amount</th>
                                <th class="text-end">VAT Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="vatRegister.loading">
                                <td colspan="7" class="text-center py-3 text-muted">Loading...</td>
                            </tr>
                            <tr v-else-if="!vatRegister.data.taxable_sales.length">
                                <td colspan="7" class="text-center py-3 text-muted">No taxable sales.</td>
                            </tr>
                            <tr v-for="(row, index) in vatRegister.data.taxable_sales" :key="row.invoice_no">
                                <td>{{ index + 1 }}</td>
                                <td>{{ row.invoice_no }}</td>
                                <td>{{ row.invoice_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.party_pan }}</td>
                                <td class="text-end">{{ formatAmount(row.taxable_amount) }}</td>
                                <td class="text-end text-danger">{{ formatAmount(row.vat_amount) }}</td>
                            </tr>
                            <tr v-if="vatRegister.data.taxable_sales.length" class="fw-bold table-light">
                                <td colspan="5">Total</td>
                                <td class="text-end">{{ formatAmount(vatRegister.data.totals.taxable) }}</td>
                                <td class="text-end text-danger">{{ formatAmount(vatRegister.data.totals.vat) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Exempt Sales Table -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-0">
                <div class="vat-table-header px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-secondary">D2 — Exempt Sales</h6>
                    <span class="badge bg-secondary-subtle text-secondary">{{ vatRegister.data.exempt_sales.length }} invoices</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 vat-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>PAN</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!vatRegister.data.exempt_sales.length">
                                <td colspan="6" class="text-center py-3 text-muted">No exempt sales.</td>
                            </tr>
                            <tr v-for="(row, index) in vatRegister.data.exempt_sales" :key="row.invoice_no">
                                <td>{{ index + 1 }}</td>
                                <td>{{ row.invoice_no }}</td>
                                <td>{{ row.invoice_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.party_pan }}</td>
                                <td class="text-end">{{ formatAmount(row.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Zero Rated Sales Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="vat-table-header px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-info">Zero Rated Sales</h6>
                    <span class="badge bg-info-subtle text-info">{{ vatRegister.data.zero_rated_sales.length }} invoices</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 vat-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>PAN</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!vatRegister.data.zero_rated_sales.length">
                                <td colspan="6" class="text-center py-3 text-muted">No zero rated sales.</td>
                            </tr>
                            <tr v-for="(row, index) in vatRegister.data.zero_rated_sales" :key="row.invoice_no">
                                <td>{{ index + 1 }}</td>
                                <td>{{ row.invoice_no }}</td>
                                <td>{{ row.invoice_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.party_pan }}</td>
                                <td class="text-end">{{ formatAmount(row.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useSalesReportStore } from '@/stores/admin/sales/report.js';
import { useAdminSettingStore } from '@/stores/admin/settings/admin-setting.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import DateRangePicker from 'daterangepicker';
import moment from 'moment';

const store = useSalesReportStore();
const settingStore = useAdminSettingStore();
const vatRegister = computed(() => store.vatRegister);

const dateRangeInput = ref(null);
const filter = reactive({ from_date: null, to_date: null });

const formatAmount = (value) => formatMoney(Number(value || 0));

const applyDateRange = (start, end) => {
    filter.from_date = start.format('YYYY-MM-DD');
    filter.to_date = end.format('YYYY-MM-DD');
    if (dateRangeInput.value) {
        dateRangeInput.value.value = `${start.format('DD-MM-YYYY')} - ${end.format('DD-MM-YYYY')}`;
    }
};

const generateReport = () => store.getVatRegister({ from_date: filter.from_date, to_date: filter.to_date });

onMounted(async () => {
    await settingStore.getFiscalYears();
    const currentFiscalYear = settingStore.fiscalYears?.data?.find((y) => y.is_current);
    filter.from_date = currentFiscalYear?.start_date || moment().startOf('month').format('YYYY-MM-DD');
    filter.to_date = currentFiscalYear?.end_date || moment().format('YYYY-MM-DD');

    new DateRangePicker(
        dateRangeInput.value,
        { startDate: moment(filter.from_date), endDate: moment(filter.to_date), autoApply: true, locale: { format: 'DD-MM-YYYY' } },
        applyDateRange
    );

    await generateReport();
});
</script>

<style scoped>
.vat-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}
</style>
