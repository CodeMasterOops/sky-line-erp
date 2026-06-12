<template>
    <VModal
        :show-modal="!!detailReceiptId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Receipt detail">
        <template #modal-body>
            <VLoader v-if="receipt.loading" loader-type="progress"/>
            <MoneyReceiptPrintLayout
                v-else-if="detailData.id"
                :detail-data="detailData"
                variant="received"
            />

            <div v-if="detailData.id && !receipt.loading" class="d-flex gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Receipt" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {useReceiptStore} from '@/stores/admin/sales/receipt.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import MoneyReceiptPrintLayout from '@/components/print/MoneyReceiptPrintLayout.vue';
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
