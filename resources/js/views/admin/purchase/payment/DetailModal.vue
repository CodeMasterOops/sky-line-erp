<template>
    <VModal
        :show-modal="!!detailPaymentId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Payment detail">
        <template #modal-body>
            <VLoader v-if="payment.loading" loader-type="progress"/>
            <MoneyReceiptPrintLayout
                v-else-if="detailData.id"
                :detail-data="detailData"
                variant="paid"
                allocation-document-key="payable"
            />

            <div v-if="detailData.id && !payment.loading" class="d-flex gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Payment" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {usePaymentStore} from '@/stores/admin/purchase/payment.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import MoneyReceiptPrintLayout from '@/components/print/MoneyReceiptPrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const paymentStore = usePaymentStore();
const {payment} = storeToRefs(paymentStore);
const {ensureBranding} = useCompanyBranding();

const detailPaymentId = defineModel('detailPaymentId', {type: String, default: ''});
const detailData = computed(() => payment.value.data || {});

watch(() => detailPaymentId.value, async (id) => {
    if (id) {
        await ensureBranding();
        paymentStore.getPayment(id);
    }
});

const closeModal = () => { detailPaymentId.value = ''; };
</script>
