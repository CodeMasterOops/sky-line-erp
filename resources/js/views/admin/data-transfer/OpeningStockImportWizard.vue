<template>
    <EntityImportWizard
        ref="wizardRef"
        entity-type="opening_stock"
        title="Import Opening Stock"
        entity-label="opening stock"
        :fields="openingStockFields"
        modal-id="opening-stock-import-wizard"
        :template-download-fn="store.downloadOpeningStockTemplate"
        @imported="$emit('imported')"
    />
</template>

<script setup>
import { ref } from 'vue';
import EntityImportWizard from '@/views/admin/data-transfer/EntityImportWizard.vue';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';

defineEmits(['imported']);

const store = useDataTransferStore();
const wizardRef = ref(null);

const openingStockFields = [
    'product_code', 'sku', 'barcode', 'warehouse', 'quantity', 'rate',
    'batch_no', 'expiry_date', 'remarks',
];

const show = () => wizardRef.value?.show();

defineExpose({ show });
</script>
