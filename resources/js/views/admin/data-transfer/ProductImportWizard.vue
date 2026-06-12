<template>
    <EntityImportWizard
        ref="wizardRef"
        entity-type="product"
        title="Import Products"
        entity-label="product"
        :fields="productFields"
        modal-id="product-import-wizard"
        :template-download-fn="store.downloadProductTemplate"
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

const productFields = [
    'name', 'code', 'product_type', 'category', 'unit', 'brand', 'tax', 'sku',
    'sales_price', 'purchase_price', 'warehouse', 'quantity',
];

const show = () => wizardRef.value?.show();

defineExpose({ show });
</script>
