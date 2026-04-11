<template>
    <PageHeader title="Sales Report" subtitle="Sales report" />

    <div class="row">
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-success sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-success text-white">
                        <i class="ti ti-currency-dollar fs-24"></i>
                    </span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Total Amount</p>
                        <div>
                            <h3>$4,56,000</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-info sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-info text-white">
                        <i class="ti ti-circle-check fs-24"></i>
                    </span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Total Paid</p>
                        <div>
                            <h3>$2,56,42</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-orange sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-orange text-white">
                        <i class="ti ti-wallet fs-24"></i>
                    </span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Total Unpaid</p>
                        <div>
                            <h3>$1,52,45</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12 d-flex">
            <div class="card border border-danger sale-widget flex-fill">
                <div class="card-body d-flex align-items-center">
                    <span class="sale-icon bg-danger text-white">
                        <i class="ti ti-alert-circle fs-24"></i>
                    </span>
                    <div class="ms-2">
                        <p class="fw-medium mb-1">Overdue</p>
                        <div>
                            <h3>$2,56,12</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card border-0">
        <div class="card-body pb-1">
            <form action="#" @submit.prevent>
                <div class="row align-items-end">
                    <div class="col-lg-10">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Choose Date</label>
                                    <div class="input-icon-start position-relative">
                                        <input
                                            ref="dateRangeInput"
                                            type="text"
                                            class="form-control date-range bookingrange"
                                            placeholder="dd/mm/yyyy - dd/mm/yyyy"
                                        />
                                        <span class="input-icon-left">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Store</label>
                                    <vue-select v-model="selectedStore" :options="storeOptions" placeholder="All" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Products</label>
                                    <vue-select v-model="selectedProduct" :options="productOptions" placeholder="All" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="mb-3">
                            <button class="btn btn-primary w-100" type="button">Generate Report</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="card table-list-card no-search">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div>
                <h4>Sales Report</h4>
            </div>
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
            <div class="table-responsive">
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
                            <td>
                                <a href="javascript:void(0);">{{ row.sku }}</a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span
                                        class="avatar avatar-md bg-light text-primary d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-package fs-18"></i>
                                    </span>
                                    <div class="ms-2">
                                        <p class="text-dark mb-0">
                                            <a href="javascript:void(0);">{{ row.name }}</a>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>{{ row.brand }}</td>
                            <td>{{ row.category }}</td>
                            <td>{{ row.soldQty }}</td>
                            <td>{{ row.soldAmount }}</td>
                            <td>{{ row.instockQty }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {onMounted, ref} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';

const dateRangeInput = ref(null);
const selectedStore = ref('All');
const selectedProduct = ref('All');

const storeOptions = [
    {label: 'All', value: 'All'},
    {label: 'Lenovo', value: 'Lenovo'},
    {label: 'Nike', value: 'Nike'},
    {label: 'Amazon', value: 'Amazon'},
];

const productOptions = [
    {label: 'All', value: 'All'},
    {label: 'Lenovo IdeaPad 3', value: 'Lenovo IdeaPad 3'},
    {label: 'Nike Jordan', value: 'Nike Jordan'},
    {label: 'Amazon Echo Dot', value: 'Amazon Echo Dot'},
    {label: 'Red Premium Satchel', value: 'Red Premium Satchel'},
];

const reportRows = [
    {sku: 'PT001', name: 'Lenovo IdeaPad 3', brand: 'Lenovo', category: 'Computers', soldQty: '05', soldAmount: '$3000', instockQty: '100'},
    {sku: 'PT002', name: 'Beats Pro', brand: 'Beats', category: 'Electronics', soldQty: '10', soldAmount: '$1600', instockQty: '140'},
    {sku: 'PT003', name: 'Nike Jordan', brand: 'Nike', category: 'Shoe', soldQty: '08', soldAmount: '$880', instockQty: '300'},
    {sku: 'PT004', name: 'Apple Series 5 Watch', brand: 'Apple', category: 'Electronics', soldQty: '10', soldAmount: '$1200', instockQty: '450'},
    {sku: 'PT005', name: 'Amazon Echo Dot', brand: 'Amazon', category: 'Electronics', soldQty: '05', soldAmount: '$400', instockQty: '320'},
    {sku: 'PT006', name: 'Sanford Chair Sofa', brand: 'Modern Wave', category: 'Furniture', soldQty: '07', soldAmount: '$2240', instockQty: '650'},
    {sku: 'PT007', name: 'Red Premium Satchel', brand: 'Dior', category: 'Bags', soldQty: '15', soldAmount: '$900', instockQty: '700'},
    {sku: 'PT008', name: 'Iphone 14 Pro', brand: 'Apple', category: 'Phone', soldQty: '12', soldAmount: '$6480', instockQty: '630'},
    {sku: 'PT009', name: 'Gaming Chair', brand: 'Arlime', category: 'Furniture', soldQty: '10', soldAmount: '$2000', instockQty: '410'},
    {sku: 'PT010', name: 'Borealis Backpack', brand: 'The North Face', category: 'Bags', soldQty: '20', soldAmount: '$900', instockQty: '550'},
];

function formatRange(start, end) {
    return `${start.format('DD/MM/YYYY')} - ${end.format('DD/MM/YYYY')}`;
}

onMounted(() => {
    const el = dateRangeInput.value;
    if (!el) {
        return;
    }

    const start = moment().subtract(6, 'days');
    const end = moment();

    const applyToInput = (rangeStart, rangeEnd) => {
        el.value = formatRange(rangeStart, rangeEnd);
    };

    new DateRangePicker(
        el,
        {
            startDate: start,
            endDate: end,
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
        applyToInput
    );

    applyToInput(start, end);
});
</script>
