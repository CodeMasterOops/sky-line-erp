<template>
    <PageHeader title="Sales Report" subtitle="Sales report" />

    <!-- Summary cards -->
    <div class="row">
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-success sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-success text-white"><i class="ti ti-currency-dollar fs-24"></i></span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Total Amount</p>
                        <h3 v-if="!loading">{{ fmt(summary.total_sales) }}</h3>
                        <span v-else class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-info sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-info text-white"><i class="ti ti-circle-check fs-24"></i></span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Total Paid</p>
                        <h3 v-if="!loading">{{ fmt(summary.total_paid) }}</h3>
                        <span v-else class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-orange sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-orange text-white"><i class="ti ti-wallet fs-24"></i></span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Total Unpaid</p>
                        <h3 v-if="!loading">{{ fmt(summary.total_unpaid) }}</h3>
                        <span v-else class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-danger sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-danger text-white"><i class="ti ti-alert-circle fs-24"></i></span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Overdue</p>
                        <h3 v-if="!loading">{{ fmt(summary.overdue) }}</h3>
                        <span v-else class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0">
        <div class="card-body pb-1">
            <form @submit.prevent="loadReport">
                <div class="row align-items-end">
                    <div class="col-lg-10">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Choose Date</label>
                                    <div class="input-icon-start position-relative">
                                        <input ref="dateRangeInput" type="text" class="form-control date-range bookingrange"
                                            placeholder="dd/mm/yyyy - dd/mm/yyyy" />
                                        <span class="input-icon-left"><i class="ti ti-calendar"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Customer</label>
                                    <vue-select
                                        v-model="selectedParty"
                                        :options="partyOptions"
                                        label="label"
                                        :reduce="o => o.value"
                                        placeholder="All Customers"
                                        :loading="loadingParties" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Product</label>
                                    <vue-select
                                        v-model="selectedVariant"
                                        :options="variantOptions"
                                        label="label"
                                        :reduce="o => o.value"
                                        placeholder="All Products"
                                        :loading="loadingVariants" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="mb-3">
                            <button class="btn btn-primary w-100" type="submit" :disabled="loading">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                                Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results table -->
    <div class="card table-list-card no-search">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div><h4>Sales Report</h4></div>
            <ul class="table-top-head">
                <li class="me-2">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf">
                        <img src="@/assets/images/icons/pdf.svg" alt="PDF" />
                    </a>
                </li>
                <li class="me-2">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" title="Excel">
                        <img src="@/assets/images/icons/excel.svg" alt="Excel" />
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" title="Print">
                        <i class="ti ti-printer"></i>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div v-if="loading" class="text-center py-4">
                <span class="spinner-border text-primary"></span>
            </div>
            <div v-else-if="!reportRows.length" class="text-center text-muted py-4">
                No data found. Adjust filters and click Generate Report.
            </div>
            <div v-else class="table-responsive">
                <table class="table datatable">
                    <thead class="thead-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th>Sold Qty</th>
                            <th>Sold Amount</th>
                            <th>Instock Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in reportRows" :key="row.sku">
                            <td><a href="javascript:void(0);">{{ row.sku }}</a></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-md bg-light text-primary d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-package fs-18"></i>
                                    </span>
                                    <div class="ms-2">
                                        <p class="text-dark mb-0"><a href="javascript:void(0);">{{ row.name }}</a></p>
                                    </div>
                                </div>
                            </td>
                            <td>{{ row.brand || '–' }}</td>
                            <td>{{ row.category || '–' }}</td>
                            <td>{{ row.sold_qty }}</td>
                            <td>{{ fmt(row.sold_amount) }}</td>
                            <td>{{ row.instock_qty }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold bg-light">
                            <td colspan="4">Total</td>
                            <td>{{ reportRows.reduce((s, r) => s + Number(r.sold_qty), 0) }}</td>
                            <td>{{ fmt(summary.total_sales) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const dateRangeInput = ref(null);
const selectedParty   = ref(null);
const selectedVariant = ref(null);
const loading         = ref(false);
const loadingParties  = ref(false);
const loadingVariants = ref(false);

const partyOptions   = ref([]);
const variantOptions = ref([]);
const reportRows     = ref([]);

const summary = ref({
    total_sales:  0,
    total_paid:   0,
    total_unpaid: 0,
    overdue:      0,
});

const dateFrom = ref('');
const dateTo   = ref('');

const fmt = (val) => Number(val || 0).toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});

async function loadParties() {
    loadingParties.value = true;
    try {
        const res = await apiAdmin('party', 'get', {type: 'customer', per_page: 500});
        partyOptions.value = (res.data.data || []).map(p => ({label: p.name, value: p.id}));
    } catch (e) {
        showErrors(e);
    } finally {
        loadingParties.value = false;
    }
}

async function loadVariants() {
    loadingVariants.value = true;
    try {
        const res = await apiAdmin('product', 'get', {per_page: 500});
        variantOptions.value = (res.data.data || []).map(p => ({label: p.name, value: p.id}));
    } catch (e) {
        showErrors(e);
    } finally {
        loadingVariants.value = false;
    }
}

async function loadReport() {
    loading.value = true;
    try {
        const params = {};
        if (dateFrom.value)     params.from              = dateFrom.value;
        if (dateTo.value)       params.to                = dateTo.value;
        if (selectedParty.value)   params.party_id       = selectedParty.value;
        if (selectedVariant.value) params.product_variant_id = selectedVariant.value;

        const res = await apiAdmin('sales-report', 'get', params);
        reportRows.value = res.data.rows || [];
        summary.value    = res.data.summary || summary.value;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadParties();
    loadVariants();
    loadReport();

    const el = dateRangeInput.value;
    if (!el) return;

    const start = moment().subtract(29, 'days');
    const end   = moment();

    dateFrom.value = start.format('YYYY-MM-DD');
    dateTo.value   = end.format('YYYY-MM-DD');

    new DateRangePicker(el, {
        startDate: start,
        endDate:   end,
        ranges: {
            Today:         [moment(), moment()],
            Yesterday:     [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days':[moment().subtract(29, 'days'), moment()],
            'This Month':  [moment().startOf('month'), moment().endOf('month')],
            'Last Month':  [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        },
    }, (s, e) => {
        dateFrom.value = s.format('YYYY-MM-DD');
        dateTo.value   = e.format('YYYY-MM-DD');
        el.value = `${s.format('DD/MM/YYYY')} - ${e.format('DD/MM/YYYY')}`;
    });

    el.value = `${start.format('DD/MM/YYYY')} - ${end.format('DD/MM/YYYY')}`;
});
</script>
