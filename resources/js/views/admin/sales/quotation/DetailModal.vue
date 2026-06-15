<template>
    <VModal
        :show-modal="!!detailQuotationId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Quotation detail">
        <template #modal-body>
            <VLoader v-if="quotation.loading" loader-type="progress"/>
            <OrderFormPrintLayout
                v-else-if="detailData.id"
                document-title="QUOTATION"
                :detail-data="printData"
                party-title="Customer"
                document-no-key="quotation_no"
                document-date-key="quotation_date"
                :extra-meta="[{ label: 'Expiry Date', key: 'expiry_date' }]"
            />

            <div v-if="detailData.id && !quotation.loading" class="d-flex gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Quotation" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {useQuotationStore} from '@/stores/admin/sales/quotation.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import OrderFormPrintLayout from '@/components/print/OrderFormPrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const quotationStore = useQuotationStore();
const {quotation} = storeToRefs(quotationStore);
const {ensureBranding} = useCompanyBranding();

const detailQuotationId = defineModel('detailQuotationId', {type: String, default: ''});
const detailData = computed(() => quotation.value.data || {});

const totals = computed(() => {
    const items = detailData.value.items || [];
    const subtotal = items.reduce((s, i) => s + Number(i.quantity || 0) * Number(i.rate || 0), 0);
    const lineDiscount = items.reduce((s, i) => s + Number(i.discount_amount || 0), 0);
    const orderDiscount = Number(detailData.value.order_discount_amount || 0);
    const tax = items.reduce((s, i) => s + Number(i.tax_amount || 0), 0);
    const discount = lineDiscount + orderDiscount;

    return {
        subtotal,
        discount_total: discount,
        tax_total: tax,
        grand_total: subtotal - discount + tax,
    };
});

const printData = computed(() => ({
    ...detailData.value,
    ...totals.value,
}));

watch(() => detailQuotationId.value, async (id) => {
    if (id) {
        await ensureBranding();
        quotationStore.getQuotation(id);
    }
});

const closeModal = () => { detailQuotationId.value = ''; };
</script>
