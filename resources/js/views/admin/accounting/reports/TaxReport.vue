<template>
    <PageHeader title="Tax Report" subtitle="Financial report" hide-action-buttons />

    <div class="card border-0 no-print">
        <div class="card-body pb-1">
            <form @submit.prevent="onSubmit">
                <div class="row align-items-end">
                    <div class="col-lg-10">
                        <div class="row">
                            <div class="col-md-3">
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
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Store</label>
                                    <Multiselect
                                        v-model="selectedStore"
                                        :options="storeOptions"
                                        placeholder="All"
                                    />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Customer</label>
                                    <Multiselect
                                        v-model="selectedCustomer"
                                        :options="customerOptions"
                                        placeholder="All"
                                    />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <Multiselect
                                        v-model="selectedPayment"
                                        :options="paymentOptions"
                                        placeholder="All"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="mb-3">
                            <button class="btn btn-primary w-100" type="submit">Generate Report</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <ReportPrintShell report-title="Sales Tax Report" subtitle="Sales tax summary">
        <div class="d-flex justify-content-end mb-2 no-print">
            <VExport
                title="tax-report"
                target="tax-report-table"
                button-label="Export Excel"
                btn-class="btn btn-sm btn-outline-success"
            />
        </div>
            <div class="table-responsive">
                <table id="tax-report-table" class="table datatable">
                    <thead class="thead-light">
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Store</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Discount</th>
                            <th>Tax Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in reportRows" :key="row.reference">
                            <td>
                                <a href="javascript:void(0);" class="text-orange">{{ row.reference }}</a>
                            </td>
                            <td>{{ row.customer }}</td>
                            <td>{{ row.date }}</td>
                            <td>{{ row.store }}</td>
                            <td>{{ formatMoney(row.amount) }}</td>
                            <td>{{ row.paymentMethod }}</td>
                            <td>{{ formatMoney(row.discount) }}</td>
                            <td>{{ formatMoney(row.taxAmount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
    </ReportPrintShell>
</template>

<script setup>
import {onMounted, ref} from 'vue';
import moment from 'moment';
import {useRangePickerLabel} from '@/composables/useRangePickerLabel.js';
import DateRangePicker from 'daterangepicker';
import {formatMoney} from '@/helpers/formatMoney.js';
import ReportPrintShell from '@/components/print/ReportPrintShell.vue';

const dateRangeInput = ref(null);
const selectedStore = ref('All');
const selectedCustomer = ref('All');
const selectedPayment = ref('All');

const storeOptions = [
    {label: 'All', value: 'All'},
    {label: 'Electro Mart', value: 'Electro Mart'},
    {label: 'Quantum Gadgets', value: 'Quantum Gadgets'},
    {label: 'Prime Bazaar', value: 'Prime Bazaar'},
];

const customerOptions = [
    {label: 'All', value: 'All'},
    {label: 'Carl Evans', value: 'Carl Evans'},
    {label: 'Minerva Ramirez', value: 'Minerva Ramirez'},
    {label: 'Robert Lamon', value: 'Robert Lamon'},
];

const paymentOptions = [
    {label: 'All', value: 'All'},
    {label: 'Stripe', value: 'Stripe'},
    {label: 'Paypal', value: 'Paypal'},
    {label: 'Cash', value: 'Cash'},
];

const reportRows = [
    {reference: '#4237300', customer: 'Carl Evans', date: '24 Dec 2024', store: 'Electro Mart', amount: 200, paymentMethod: 'Stripe', discount: 200, taxAmount: 200},
    {reference: '#7590325', customer: 'Minerva Ramirez', date: '10 Dec 2024', store: 'Quantum Gadgets', amount: 50, paymentMethod: 'Paypal', discount: 50, taxAmount: 50},
    {reference: '#9814521', customer: 'Robert Lamon', date: '27 Nov 2024', store: 'Prime Bazaar', amount: 800, paymentMethod: 'Cash', discount: 800, taxAmount: 800},
    {reference: '#8745225', customer: 'Patricia Lewis', date: '18 Nov 2024', store: 'Gadget World', amount: 100, paymentMethod: 'Paypal', discount: 100, taxAmount: 100},
    {reference: '#4237022', customer: 'Mark Joslyn', date: '06 Nov 2024', store: 'Volt Vault', amount: 700, paymentMethod: 'Cash', discount: 700, taxAmount: 700},
    {reference: '#8744439', customer: 'Marsha Betts', date: '25 Oct 2024', store: 'Elite Retail', amount: 1000, paymentMethod: 'Cash', discount: 1000, taxAmount: 1000},
    {reference: '#7590365', customer: 'Daniel Jude', date: '14 Oct 2024', store: 'Prime Mart', amount: 1200, paymentMethod: 'Paypal', discount: 1200, taxAmount: 1200},
    {reference: '#8745478', customer: 'Emma Bates', date: '03 Oct 2024', store: 'NeoTech Store', amount: 750, paymentMethod: 'Stripe', discount: 750, taxAmount: 750},
    {reference: '#7590321', customer: 'Richard Fralick', date: '20 Sep 2024', store: 'Urban Mart', amount: 450, paymentMethod: 'Stripe', discount: 450, taxAmount: 450},
    {reference: '#8745245', customer: 'Michelle Robison', date: '10 Sep 2024', store: 'Travel Mart', amount: 300, paymentMethod: 'Cash', discount: 300, taxAmount: 300},
];

const {formatPickerValue} = useRangePickerLabel();

function formatRange(start, end) {
    return formatPickerValue(start, end, 'DD/MM/YYYY');
}

function onSubmit() {
    /* Wire to API when ready */
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
