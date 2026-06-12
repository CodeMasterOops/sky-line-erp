<template>
    <VModal
        :show-modal="!!detailReceiptId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Receipt detail">
        <template #modal-body>
            <VLoader v-if="receipt.loading" loader-type="progress"/>
            <DocumentPrintLayout
                v-else-if="detailData.id"
                document-title="Receipt"
                :document-no="detailData.receipt_no || ''"
                :document-date="detailData.receipt_date || ''"
            >
                <template #header-meta>
                    <p class="mb-1">Reference: {{ detailData.reference_no || '—' }}</p>
                    <span class="badge" :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                        {{ detailData.status }}
                    </span>
                </template>

                <template #parties>
                    <DocumentPrintParties :party-name="detailData.party_name" />
                </template>

                <template #body>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Payment Method:</strong> {{ detailData.payment_method || '—' }}</p>
                            <p class="mb-0"><strong>Account:</strong> {{ detailData.account_name || '—' }}</p>
                        </div>
                    </div>
                    <p v-if="detailData.remarks" class="mb-3"><strong>Remarks:</strong> {{ detailData.remarks }}</p>

                    <h6 class="mb-2">Allocations</h6>
                    <div class="table-responsive">
                        <table class="table datanew table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>SN</th>
                                <th>Invoice</th>
                                <th>Invoice Date</th>
                                <th class="text-end">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(alloc, index) in (detailData.allocations || [])" :key="alloc.id || index">
                                <td>{{ index + 1 }}</td>
                                <td>{{ alloc.invoice?.invoice_no || alloc.invoice_id || '—' }}</td>
                                <td>{{ alloc.invoice?.invoice_date || '—' }}</td>
                                <td class="text-end">{{ formatMoney(alloc.amount) }}</td>
                            </tr>
                            <tr v-if="!(detailData.allocations || []).length">
                                <td colspan="4" class="text-center text-muted">No allocations</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #totals>
                    <div class="col-md-4 ms-auto text-end">
                        <h5>Total: {{ formatMoney(detailData.total_amount) }}</h5>
                    </div>
                </template>
            </DocumentPrintLayout>

            <div v-if="detailData.id && !receipt.loading" class="d-flex gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Receipt" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {useReceiptStore} from '@/stores/admin/sales/receipt.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintParties from '@/components/print/DocumentPrintParties.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const receiptStore = useReceiptStore();
const {receipt} = storeToRefs(receiptStore);
const {ensureBranding} = useCompanyBranding();

const detailReceiptId = defineModel('detailReceiptId', {type: String, default: ''});
const detailData = computed(() => receipt.value.data || {});

watch(() => detailReceiptId.value, async (id) => {
    if (id) {
        await ensureBranding();
        receiptStore.getReceipt(id);
    }
});

const closeModal = () => { detailReceiptId.value = ''; };
</script>
