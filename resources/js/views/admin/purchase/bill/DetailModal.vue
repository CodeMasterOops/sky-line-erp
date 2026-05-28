<template>
    <VModal
        :show-modal="!!detailBillId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Bill detail">
        <template #modal-body>
            <VLoader v-if="bill.loading" loader-type="progress"/>
            <div v-else-if="detailData.id" class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <div class="sales-details-items d-flex flex-wrap gap-3 mb-4">
                        <div class="details-item">
                            <h6>Supplier</h6>
                            <p class="mb-0 fw-medium">{{ detailData.party_name || '—' }}</p>
                        </div>
                        <div class="details-item">
                            <h6>Bill</h6>
                            <p class="mb-0">
                                <span class="fw-medium">{{ detailData.bill_no }}</span><br>
                                <span class="text-muted small">{{ detailData.bill_date }}</span><br>
                                <span class="text-muted small">Due: {{ detailData.due_date || '—' }}</span>
                            </p>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span
                                    class="badge"
                                    :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                    {{ detailData.status }}
                                </span>
                                <span
                                    v-if="detailData.voided_at"
                                    class="badge bg-dark">
                                    voided
                                </span>
                            </div>
                        </div>
                        <div
                            v-if="detailData.status === 'approved' && !detailData.voided_at"
                            class="details-item">
                            <h6>Payment</h6>
                            <span
                                class="badge fs-10"
                                :class="paymentBadgeClass">
                                {{ paymentBadgeLabel }}
                            </span>
                            <p
                                v-if="detailData.paid_total != null"
                                class="mb-0 mt-2 small text-muted">
                                Paid {{ formatMoney(detailData.paid_total) }}
                                <template v-if="detailData.due_amount != null">
                                    · Due {{ formatMoney(detailData.due_amount) }}
                                </template>
                            </p>
                        </div>
                        <div class="details-item">
                            <h6>Remarks</h6>
                            <p class="mb-0">{{ detailData.remarks || '—' }}</p>
                        </div>
                    </div>

                    <h5 class="order-text mb-3">Line items</h5>
                    <div class="table-responsive no-pagination">
                        <table class="table datanew table-bordered table-hover mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width: 48px">SN</th>
                                <th>Product</th>
                                <th>GRN</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Rate</th>
                                <th class="text-end">Discount</th>
                                <th>Tax</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr
                                v-for="(item, index) in (detailData.items || [])"
                                :key="item.id || index">
                                <td class="text-center">{{ index + 1 }}</td>
                                <td class="text-start">{{ productLabel(item) }}</td>
                                <td class="text-muted small">{{ item.grn_no || '—' }}</td>
                                <td class="text-end">{{ item.quantity }}</td>
                                <td class="text-end">{{ formatMoney(item.rate) }}</td>
                                <td class="text-end">{{ formatMoney(item.discount_amount) }}</td>
                                <td>{{ taxLabel(item) }}</td>
                            </tr>
                            <tr v-if="!(detailData.items || []).length">
                                <td colspan="7" class="text-center text-muted py-3">No line items</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="displayLandedCosts.length" class="mt-4">
                        <h5 class="order-text mb-3">Additional charges / landed costs</h5>
                        <div class="table-responsive no-pagination">
                            <table class="table datanew table-bordered mb-0">
                                <thead class="thead-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Treatment</th>
                                    <th>Allocation</th>
                                    <th>Account</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">VAT</th>
                                    <th class="text-end">Claimable VAT</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="(cost, idx) in displayLandedCosts" :key="cost.id || idx">
                                    <td>{{ cost.cost_type }}</td>
                                    <td>{{ cost.treatment }}</td>
                                    <td>{{ cost.allocation_method || '—' }}</td>
                                    <td>{{ cost.account?.name || '—' }}</td>
                                    <td class="text-end">{{ formatMoney(cost.amount) }}</td>
                                    <td class="text-end">{{ formatMoney(cost.vat_amount) }}</td>
                                    <td class="text-end">{{ formatMoney(cost.vat_claimable_amount) }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-lg-5 ms-auto">
                            <div class="border rounded p-3 bg-light">
                                <div
                                    v-for="row in totalRows"
                                    :key="row.label"
                                    class="d-flex justify-content-between align-items-center mb-2"
                                    :class="{ 'border-top pt-2 mt-2': row.emphasis }">
                                    <span :class="row.emphasis ? 'fw-semibold' : 'text-muted'">{{ row.label }}</span>
                                    <span :class="row.emphasis ? 'fw-semibold' : ''">{{ formatMoney(row.value) }}</span>
                                </div>
                                <template v-if="showPaymentTotals">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted">Paid</span>
                                        <span class="text-success">{{ formatMoney(detailData.paid_total) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Amount due</span>
                                        <span
                                            class="fw-semibold"
                                            :class="Number(detailData.due_amount) > 0 ? 'text-danger' : 'text-success'">
                                            {{ formatMoney(detailData.due_amount) }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="detailData.id"
                        class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                        <button
                            v-if="canRecordPayment"
                            type="button"
                            class="btn btn-primary btn-sm"
                            @click="emitRecordPayment">
                            <i class="ti ti-receipt me-1"></i>Record payment
                        </button>
                        <button
                            v-can="'approve_bill'"
                            v-if="detailData.status === 'approved' && !detailData.voided_at"
                            type="button"
                            class="btn btn-warning btn-sm text-dark"
                            @click="voidBill">
                            <i class="ti ti-ban me-1"></i>Void bill
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useBillStore} from '@/stores/admin/purchase/bill.js';

const emit = defineEmits(['voided', 'record-payment']);

const billStore = useBillStore();
const {bill} = storeToRefs(billStore);

const detailBillId = defineModel('detailBillId', {type: String, default: ''});

const detailData = computed(() => bill.value.data || {});

const detailTotalDiscount = computed(() => {
    const d = detailData.value;
    const line = Number(d.discount_total ?? d.line_discount_total ?? 0) || 0;
    const orderAmt = Number(d.order_discount_amount ?? 0) || 0;
    return line + orderAmt;
});

const displayLandedCosts = computed(() => {
    const d = detailData.value;
    if ((d.landed_costs || []).length) {
        return d.landed_costs;
    }
    return d.grn_landed_costs || [];
});

const showPaymentTotals = computed(() =>
    detailData.value.status === 'approved'
    && !detailData.value.voided_at
    && detailData.value.paid_total != null
    && detailData.value.due_amount != null,
);

const canRecordPayment = computed(() =>
    showPaymentTotals.value && Number(detailData.value.due_amount ?? 0) > 0,
);

const totalRows = computed(() => {
    const d = detailData.value;
    const rows = [
        {label: 'Sub total', value: d.subtotal},
        {label: 'Discount', value: detailTotalDiscount.value},
        {label: 'Non-taxable (net)', value: d.non_taxable_base},
        {label: 'Taxable (net)', value: d.taxable_base},
        {label: 'Tax', value: d.tax_total},
        {label: 'Grand total', value: d.grand_total, emphasis: true},
    ];

    return rows.filter((row) => row.value != null && row.value !== '');
});

const paymentBadgeLabel = computed(() => {
    if (detailData.value.voided_at) {
        return 'Voided';
    }
    if (detailData.value.status !== 'approved') {
        return detailData.value.status || '—';
    }
    const due = detailData.value.due_amount;
    const paid = detailData.value.paid_total;
    const grand = Number(detailData.value.grand_total || 0);
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
    if (detailData.value.voided_at) {
        return 'bg-dark';
    }
    if (detailData.value.status !== 'approved') {
        return 'bg-secondary';
    }
    const due = detailData.value.due_amount;
    const paid = detailData.value.paid_total;
    const grand = Number(detailData.value.grand_total || 0);
    if (due == null || paid == null) {
        return 'bg-success';
    }
    if (grand <= 0) {
        return due <= 0 ? 'bg-success' : 'bg-danger';
    }
    if (due <= 0) {
        return 'bg-success';
    }
    if (paid > 0) {
        return 'bg-warning text-dark';
    }
    return 'bg-danger';
});

watch(
    () => detailBillId.value,
    (id) => {
        if (id) {
            billStore.getBill(id);
        }
    },
);

const closeModal = () => {
    detailBillId.value = '';
};

const emitRecordPayment = () => {
    const id = detailData.value.id;
    if (id) {
        emit('record-payment', id);
    }
};

const productLabel = (item) => {
    if (item.product_variant?.name) {
        return item.product_variant.name;
    }
    return '—';
};

const taxLabel = (item) => {
    if (item.tax?.name) {
        const r = item.tax.rate != null ? `${item.tax.rate}%` : '';
        return r ? `${item.tax.name} (${r})` : item.tax.name;
    }
    return '—';
};

const voidBill = async () => {
    const id = detailData.value.id;
    if (!id) {
        return;
    }
    Swal.fire({
        title: 'Void bill?',
        text: 'This reverses inventory and marks the bill void.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await billStore.voidBill(id);
                toast(res.status, res.data?.message ?? 'Bill voided.');
                emit('voided');
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
