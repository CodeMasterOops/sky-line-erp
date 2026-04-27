<template>
    <VModal
        :show-modal="!!detailDebitNoteId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Debit note detail">
        <template #modal-body>
            <VLoader v-if="debitNote.loading" loader-type="progress"/>
            <div v-else-if="detailData.id" class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <div class="sales-details-items d-flex flex-wrap gap-3 mb-4">
                        <div class="details-item">
                            <h6>Supplier</h6>
                            <p class="mb-0">{{ detailData.party_name || '—' }}</p>
                        </div>
                        <div class="details-item">
                            <h6>Debit note</h6>
                            <p class="mb-0">
                                {{ detailData.debit_note_no }}<br>
                                {{ detailData.debit_note_date }}<br>
                                <span class="text-muted small">
                                    Bill: {{ detailData.bill_no || '—' }}
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
                    <h5 class="order-text mb-3">Debit note summary</h5>
                    <div class="table-responsive no-pagination">
                        <table class="table datanew table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>SN</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Rate</th>
                                <th>Discount</th>
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
                                    <li>
                                        <h4>Discount</h4>
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
                            v-can="'approve_debit_note'"
                            v-if="detailData.status === 'approved' && !detailData.voided_at"
                            type="button"
                            class="btn btn-warning btn-sm text-dark"
                            @click="voidDebitNote">
                            <i class="ti ti-ban me-1"></i>Void debit note
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
import {useDebitNoteStore} from '@/stores/admin/purchase/debit-note.js';

const emit = defineEmits(['voided']);

const debitNoteStore = useDebitNoteStore();
const {debitNote} = storeToRefs(debitNoteStore);

const detailDebitNoteId = defineModel('detailDebitNoteId', {type: String, default: ''});

const detailData = computed(() => debitNote.value.data || {});

watch(
    () => detailDebitNoteId.value,
    (id) => {
        if (id) {
            debitNoteStore.getDebitNote(id);
        }
    }
);

const closeModal = () => {
    detailDebitNoteId.value = '';
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

const voidDebitNote = async () => {
    const id = detailData.value.id;
    if (!id) {
        return;
    }
    Swal.fire({
        title: 'Void debit note?',
        text: 'This reverses inventory and marks the debit note void.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await debitNoteStore.voidDebitNote(id);
                toast(res.status, res.data?.message ?? 'Debit note voided.');
                emit('voided');
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
