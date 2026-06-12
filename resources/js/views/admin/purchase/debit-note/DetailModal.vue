<template>
    <VModal
        :show-modal="!!detailDebitNoteId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Debit note detail">
        <template #modal-body>
            <VLoader v-if="debitNote.loading" loader-type="progress"/>
            <DocumentPrintLayout
                v-else-if="detailData.id"
                document-title="Debit Note"
                :document-no="detailData.debit_note_no || ''"
                :document-date="formatDate(detailData.debit_note_date)"
            >
                <template #header-meta>
                    <p class="mb-1 text-muted small">Bill: {{ detailData.bill_no || '—' }}</p>
                    <p class="mb-0">
                        <span class="badge" :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                            {{ detailData.status }}
                        </span>
                        <span v-if="detailData.voided_at" class="badge bg-dark ms-1">voided</span>
                    </p>
                </template>

                <template #parties>
                    <DocumentPrintParties :party-name="detailData.party_name" />
                </template>

                <template #body>
                    <p v-if="detailData.remarks" class="mb-3"><strong>Remarks:</strong> {{ detailData.remarks }}</p>
                    <h5 class="order-text mb-3">Line items</h5>
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
                                <td>{{ formatMoney(item.rate) }}</td>
                                <td>{{ formatMoney(item.discount_amount) }}</td>
                                <td>{{ taxLabel(item) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #totals>
                    <div class="col-lg-6 ms-auto">
                        <div class="total-order w-100 max-widthauto m-auto mb-2">
                            <ul>
                                <li><h4>Sub total</h4><h5>{{ formatMoney(detailData.subtotal) }}</h5></li>
                                <li><h4>Discount</h4><h5>{{ formatMoney(detailData.discount_total) }}</h5></li>
                                <li><h4>Tax</h4><h5>{{ formatMoney(detailData.tax_total) }}</h5></li>
                                <li><h4>Grand total</h4><h5>{{ formatMoney(detailData.grand_total) }}</h5></li>
                            </ul>
                        </div>
                    </div>
                </template>
            </DocumentPrintLayout>

            <div v-if="detailData.id && !debitNote.loading" class="d-flex flex-wrap gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Debit Note" label="Print" button-class="btn-sm" />
                <button
                    v-can="'approve_debit_note'"
                    v-if="detailData.status === 'approved' && !detailData.voided_at"
                    type="button"
                    class="btn btn-warning btn-sm text-dark"
                    @click="voidDebitNote">
                    <i class="ti ti-ban me-1"></i>Void debit note
                </button>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {formatDate} from '@/helpers/helper.js';
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useDebitNoteStore} from '@/stores/admin/purchase/debit-note.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintParties from '@/components/print/DocumentPrintParties.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const emit = defineEmits(['voided']);

const debitNoteStore = useDebitNoteStore();
const {debitNote} = storeToRefs(debitNoteStore);
const {ensureBranding} = useCompanyBranding();

const detailDebitNoteId = defineModel('detailDebitNoteId', {type: String, default: ''});
const detailData = computed(() => debitNote.value.data || {});

watch(() => detailDebitNoteId.value, async (id) => {
    if (id) {
        await ensureBranding();
        debitNoteStore.getDebitNote(id);
    }
});

const closeModal = () => { detailDebitNoteId.value = ''; };
const productLabel = (item) => item.product_variant?.name || '—';
const taxLabel = (item) => {
    if (item.tax?.name) {
        const r = item.tax.rate != null ? `${item.tax.rate}%` : '';
        return r ? `${item.tax.name} (${r})` : item.tax.name;
    }
    return '—';
};

const voidDebitNote = async () => {
    const id = detailData.value.id;
    if (!id) return;
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
