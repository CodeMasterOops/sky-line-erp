<template>
    <VModal
        :show-modal="!!detailOrderId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Purchase order detail">
        <template #modal-body>
            <VLoader v-if="order.loading" loader-type="progress"/>
            <OrderFormPrintLayout
                v-else-if="detailData.id"
                document-title="PURCHASE ORDER"
                :detail-data="detailData"
                party-title="Supplier"
            />

            <div v-if="detailData.id && !order.loading" class="d-flex flex-wrap gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Purchase Order" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {usePurchaseOrderStore} from '@/stores/admin/purchase/purchase-order.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import OrderFormPrintLayout from '@/components/print/OrderFormPrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const purchaseOrderStore = usePurchaseOrderStore();
const {order} = storeToRefs(purchaseOrderStore);
const {ensureBranding} = useCompanyBranding();

const detailOrderId = defineModel('detailOrderId', {type: String, default: ''});
const detailData = computed(() => order.value.data || {});

watch(() => detailOrderId.value, async (id) => {
    if (id) {
        await ensureBranding();
        purchaseOrderStore.getOrder(id);
    }
});

const closeModal = () => { detailOrderId.value = ''; };
</script>
