<template>
    <PageHeader :title="headerTitle" :subtitle="headerSubtitle" />
    <VLoader v-if="invoice.loading" loader-type="progress" />
    <template v-else>
        <div id="invoice-print-area" class="card">
            <div class="card-body">
                <div class="row justify-content-between align-items-center border-bottom mb-3">
                    <div class="col-md-6">
                        <div class="mb-3 invoice-logo d-flex">
                            <template v-if="setting.data.logo_url">
                                <img
                                    :src="setting.data.logo_url"
                                    width="130"
                                    class="img-fluid"
                                    alt="logo"
                                >
                            </template>
                            <template v-else>
                                <img src="@/assets/images/logo.svg" width="130" class="img-fluid dark-logo" alt="logo">
                                <img src="@/assets/images/logo-white.svg" width="130" class="img-fluid white-logo" alt="logo">
                            </template>
                        </div>
                        <p class="mb-0">{{ companyAddressLine }}</p>
                    </div>
                    <div class="col-md-6">
                        <div class="text-end mb-3">
                            <h5 class="text-gray mb-1">
                                Invoice No <span class="text-primary">#{{ inv.invoice_no || '—' }}</span>
                            </h5>
                            <p class="mb-1 fw-medium">
                                Created Date : <span class="text-dark">{{ formatDate(inv.invoice_date) }}</span>
                            </p>
                            <p class="fw-medium">
                                Due Date : <span class="text-dark">{{ formatDate(inv.due_date) }}</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="row border-bottom mb-3">
                    <div class="col-md-5">
                        <p class="text-dark mb-2 fw-semibold">From</p>
                        <div>
                            <h4 class="mb-1">{{ setting.data.company_name || '—' }}</h4>
                            <p class="mb-1">{{ setting.data.address || '' }}</p>
                            <p v-if="setting.data.email" class="mb-1">
                                Email : <span class="text-dark">{{ setting.data.email }}</span>
                            </p>
                            <p v-if="setting.data.phone">
                                Phone : <span class="text-dark">{{ setting.data.phone }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <p class="text-dark mb-2 fw-semibold">To</p>
                        <div>
                            <h4 class="mb-1">{{ customerName }}</h4>
                            <p v-if="customerAddress" class="mb-1">{{ customerAddress }}</p>
                            <p v-if="customerEmail" class="mb-1">
                                Email : <span class="text-dark">{{ customerEmail }}</span>
                            </p>
                            <p v-if="customerPhone">
                                Phone : <span class="text-dark">{{ customerPhone }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <p class="text-title mb-2 fw-medium">Payment Status</p>
                            <span
                                class="fs-10 px-1 rounded"
                                :class="paymentBadgeClass">
                                <i class="ti ti-point-filled"></i>{{ paymentBadgeLabel }}
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="fw-medium">
                        Invoice For :
                        <span class="text-dark fw-medium">{{ invoiceForLabel }}</span>
                    </p>
                    <div class="table-responsive mb-3">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Job Description</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Cost</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in lineItems" :key="item.id || idx">
                                    <td>
                                        <h6 class="mb-0">{{ lineDescription(item) }}</h6>
                                    </td>
                                    <td class="text-gray-9 fw-medium text-end">{{ formatMoney(item.quantity) }}</td>
                                    <td class="text-gray-9 fw-medium text-end">{{ formatMoney(item.rate) }}</td>
                                    <td class="text-gray-9 fw-medium text-end">{{ formatMoney(item.discount_amount) }}</td>
                                    <td class="text-gray-9 fw-medium text-end">{{ formatMoney(lineTotal(item)) }}</td>
                                </tr>
                                <tr v-if="!lineItems.length">
                                    <td colspan="5" class="text-center text-muted">No line items</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="row border-bottom mb-3">
                    <div class="col-md-5 ms-auto mb-3">
                        <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pe-3">
                            <p class="mb-0">Sub Total</p>
                            <p class="text-dark fw-medium mb-2">${{ formatMoney(inv.subtotal) }}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pe-3">
                            <p class="mb-0">Discount ({{ discountPercentLabel }})</p>
                            <p class="text-dark fw-medium mb-2">${{ formatMoney(inv.discount_total) }}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pe-3">
                            <p class="mb-0">{{ taxSummaryLabel }}</p>
                            <p class="text-dark fw-medium mb-2">${{ formatMoney(inv.tax_total) }}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pe-3">
                            <h5>Total Amount</h5>
                            <h5>${{ formatMoney(inv.grand_total) }}</h5>
                        </div>
                        <p v-if="inv.paid_total != null && inv.status === 'approved'" class="fs-12 mb-1">
                            Paid : ${{ formatMoney(inv.paid_total) }}
                            <span v-if="inv.due_amount != null"> · Due : ${{ formatMoney(inv.due_amount) }}</span>
                        </p>
                    </div>
                </div>
                <div class="row align-items-center border-bottom mb-3">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <h6 class="mb-1">Terms and Conditions</h6>
                            <p class="mb-0">
                                Please pay within the agreed terms from the date of invoice. Late payments may incur
                                interest or fees as per your agreement.
                            </p>
                        </div>
                        <div v-if="inv.remarks" class="mb-3">
                            <h6 class="mb-1">Notes</h6>
                            <p class="mb-0">{{ inv.remarks }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="setting.data.company_name || setting.data.legal_name" class="text-center">
                    <div v-if="!setting.data.logo_url" class="mb-3 invoice-logo d-flex justify-content-center">
                        <img src="@/assets/images/logo.svg" width="130" class="img-fluid dark-logo" alt="logo">
                        <img src="@/assets/images/logo-white.svg" width="130" class="img-fluid white-logo" alt="logo">
                    </div>
                    <p class="text-dark mb-1">
                        Payment via bank transfer / cheque in the name of
                        {{ setting.data.legal_name || setting.data.company_name }}
                    </p>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center align-items-center mb-4 no-print">
            <button
                type="button"
                class="btn btn-primary d-flex justify-content-center align-items-center me-2"
                @click="printInvoice">
                <i class="ti ti-printer me-2"></i>Print Invoice
            </button>
            <router-link
                :to="{ name: 'admin.invoice-list' }"
                class="btn btn-secondary d-flex justify-content-center align-items-center border">
                <i class="ti ti-arrow-left me-2"></i>Back to invoices
            </router-link>
        </div>
    </template>
</template>

<script setup>
import {computed, watch} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import {storeToRefs} from 'pinia';
import moment from 'moment';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';
import {useSettingStore} from '@/stores/admin/setting.js';

const route = useRoute();
const router = useRouter();
const invoiceStore = useInvoiceStore();
const settingStore = useSettingStore();
const {invoice} = storeToRefs(invoiceStore);
const {setting} = storeToRefs(settingStore);

const inv = computed(() => invoice.value.data || {});

const lineItems = computed(() => {
    const items = inv.value.items;
    return Array.isArray(items) ? items : [];
});

const headerTitle = computed(() =>
    inv.value.invoice_no ? `Invoice ${inv.value.invoice_no}` : 'Invoice view',
);

const headerSubtitle = computed(() => {
    if (inv.value.party_name) {
        return `Customer: ${inv.value.party_name}`;
    }
    return 'Invoice preview';
});

const companyAddressLine = computed(() => setting.value.data?.address || '');

const customer = computed(() => inv.value.party || null);

const customerName = computed(() => customer.value?.name || inv.value.party_name || '—');

const customerAddress = computed(() => customer.value?.address || '');

const customerEmail = computed(() => customer.value?.email || '');

const customerPhone = computed(() => customer.value?.phone || '');

const invoiceForLabel = computed(() => {
    const names = lineItems.value
        .map((i) => i.product_variant?.name)
        .filter(Boolean);
    if (names.length) {
        const head = names.slice(0, 3).join(', ');
        return names.length > 3 ? `${head}…` : head;
    }
    return 'Itemized goods / services as listed below';
});

const discountPercentLabel = computed(() => {
    const sub = Number(inv.value.subtotal || 0);
    const disc = Number(inv.value.discount_total || 0);
    if (sub <= 0 || disc <= 0) {
        return '0%';
    }
    return `${((disc / sub) * 100).toFixed(1)}%`;
});

const taxSummaryLabel = computed(() => {
    const sub = Number(inv.value.subtotal || 0);
    const disc = Number(inv.value.discount_total || 0);
    const tax = Number(inv.value.tax_total || 0);
    const base = sub - disc;
    if (base > 0 && tax > 0) {
        return `Tax (${((tax / base) * 100).toFixed(1)}%)`;
    }
    return 'Tax';
});

const paymentBadgeLabel = computed(() => {
    if (inv.value.status === 'draft') {
        return 'Draft';
    }
    if (inv.value.status !== 'approved') {
        return inv.value.status || '—';
    }
    const due = inv.value.due_amount;
    const paid = inv.value.paid_total;
    const grand = Number(inv.value.grand_total || 0);
    if (due == null || paid == null) {
        return 'Approved';
    }
    if (grand <= 0) {
        return due <= 0 ? 'Paid' : 'Unpaid';
    }
    if (due <= 0) {
        return 'Paid';
    }
    if (paid > 0) {
        return 'Partial';
    }
    return 'Unpaid';
});

const paymentBadgeClass = computed(() => {
    if (inv.value.status === 'draft') {
        return 'bg-secondary text-white';
    }
    if (inv.value.status !== 'approved') {
        return 'bg-secondary text-white';
    }
    const due = inv.value.due_amount;
    const paid = inv.value.paid_total;
    const grand = Number(inv.value.grand_total || 0);
    if (due == null || paid == null) {
        return 'bg-success text-white';
    }
    if (grand <= 0) {
        return due <= 0 ? 'bg-success text-white' : 'bg-danger text-white';
    }
    if (due <= 0) {
        return 'bg-success text-white';
    }
    if (paid > 0) {
        return 'bg-warning text-dark';
    }
    return 'bg-danger text-white';
});

const formatDate = (value) => {
    if (!value) {
        return '—';
    }
    return moment(value).format('MMM D, YYYY');
};

const formatMoney = (value) => {
    if (value === '' || value === null || value === undefined) {
        return '—';
    }
    return Number(value).toFixed(2);
};

const lineDescription = (item) => item.product_variant?.name || 'Item';

const lineTotal = (item) => {
    const qty = Number(item.quantity || 0);
    const rate = Number(item.rate || 0);
    const lineDiscount = Number(item.discount_amount || 0);
    const tax = Number(item.tax_amount || 0);
    return qty * rate - lineDiscount + tax;
};

const loadPage = async () => {
    const id = route.params.id;
    if (!id) {
        router.push({name: 'admin.invoice-list'});
        return;
    }
    await settingStore.getSetting();
    await invoiceStore.getInvoice(id);
    if (!invoice.value.data?.id) {
        router.push({name: 'admin.invoice-list'});
    }
};

watch(() => route.params.id, loadPage, {immediate: true});

const printInvoice = () => {
    window.print();
};
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
