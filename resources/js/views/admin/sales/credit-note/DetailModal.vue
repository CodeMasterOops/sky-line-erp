<template>
    <VModal
        :show-modal="!!detailCreditNoteId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Credit note detail">
        <template #modal-body>
            <VLoader v-if="creditNote.loading" loader-type="progress"/>
            <div v-else-if="detailData.id" class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <div class="sales-details-items d-flex flex-wrap gap-3 mb-4">
                        <div class="details-item">
                            <h6>Customer</h6>
                            <p class="mb-0">{{ detailData.party_name || '—' }}</p>
                        </div>
                        <div class="details-item">
                            <h6>Credit note</h6>
                            <p class="mb-0">
                                {{ detailData.credit_note_no }}<br>
                                {{ detailData.credit_note_date }}<br>
                                <span class="text-muted small">
                                    Invoice: {{ detailData.invoice_no || '—' }}
                                </span><br>
                                <span
                                    class="badge"
                                    :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                    {{ detailData.status }}
                                </span>
                                <span
                                    v-if="detailData.voided_at"
                                    class="badge bg-dark ms-1">
                                    voided
                                </span>
                            </p>
                        </div>
                        <div class="details-item">
                            <h6>Remarks</h6>
                            <p class="mb-0">{{ detailData.remarks || '—' }}</p>
                        </div>
                    </div>
                    <h5 class="order-text mb-3">Credit note summary</h5>
                    <div class="table-responsive no-pagination">
                        <table class="table datanew table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>SN</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Rate</th>
                                <th title="Line discount (amount)">Line disc.</th>
                                <th>Tax</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in (detailData.items || [])" :key="item.id || index">
                                <td>{{ index + 1 }}</td>
                                <td class="text-start">{{ productLabel(item) }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ formatN(item.rate) }}</td>
                                <td>{{ formatN(item.discount_amount) }}</td>
                                <td>{{ taxLabel(item) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="row mt-3">
                        <div class="col-lg-6 ms-auto">
                            <div class="total-order w-100 max-widthauto m-auto mb-2">
                                <ul>
                                    <li>
                                        <h4>Sub total</h4>
                                        <h5>{{ formatN(detailData.subtotal) }}</h5>
                                    </li>
                                    <li v-if="detailData.order_discount_amount != null && Number(detailData.order_discount_amount) !== 0">
                                        <h4>Order discount</h4>
                                        <h5>{{ formatN(detailData.order_discount_amount) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Discount (total)</h4>
                                        <h5>{{ formatN(detailData.discount_total) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Tax</h4>
                                        <h5>{{ formatN(detailData.tax_total) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Grand total</h4>
                                        <h5>{{ formatN(detailData.grand_total) }}</h5>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="detailData.id"
                        class="d-flex flex-wrap gap-2 mt-3">
                        <button
                            v-can="'approve_credit_note'"
                            v-if="detailData.status === 'approved' && !detailData.voided_at"
                            type="button"
                            class="btn btn-warning btn-sm text-dark"
                            @click="voidCreditNote">
                            <i class="ti ti-ban me-1"></i>Void credit note
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useCreditNoteStore} from '@/stores/admin/sales/credit-note.js';

const emit = defineEmits(['voided']);

const creditNoteStore = useCreditNoteStore();
const {creditNote} = storeToRefs(creditNoteStore);

const detailCreditNoteId = defineModel('detailCreditNoteId', {type: String, default: ''});

const detailData = computed(() => creditNote.value.data || {});

watch(
    () => detailCreditNoteId.value,
    (id) => {
        if (id) {
            creditNoteStore.getCreditNote(id);
        }
    }
);

const closeModal = () => {
    detailCreditNoteId.value = '';
};

const formatN = (v) => {
    if (v === null || v === undefined || v === '') {
        return '—';
    }
    return Number(v).toFixed(2);
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

const voidCreditNote = async () => {
    const id = detailData.value.id;
    if (!id) {
        return;
    }
    Swal.fire({
        title: 'Void credit note?',
        text: 'This reverses return inventory and marks the credit note void.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await creditNoteStore.voidCreditNote(id);
                toast(res.status, res.data?.message ?? 'Credit note voided.');
                emit('voided');
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
