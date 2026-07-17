<template>
    <EntityImportWizard
        ref="wizardRef"
        entity-type="opening_stock"
        title="Import Opening Stock"
        entity-label="opening stock"
        :fields="openingStockFields"
        modal-id="opening-stock-import-wizard"
        :template-download-fn="store.downloadOpeningStockTemplate"
        :upload-options="uploadOptions"
        :upload-disabled="!warehouseId"
        @imported="$emit('imported')"
    >
        <template #upload-extra>
            <div class="border rounded p-3 mb-3 bg-light-subtle">
                <VMultiselect
                    id="opening_stock_warehouse"
                    v-model="warehouseId"
                    :options="warehouseOptionsTree"
                    label="Warehouse"
                    required
                    placeholder="Choose the warehouse to import into"
                />
                <p class="text-muted small mb-2 mt-2">
                    Already have your products in the system? Download a worksheet with every
                    product filled in — just enter quantities and re-upload.
                </p>
                <a href="#" class="me-2" @click.prevent="downloadWorksheet('csv')">Download worksheet (CSV)</a>
                <a href="#" @click.prevent="downloadWorksheet('xlsx')">Download worksheet (XLSX)</a>
            </div>
        </template>
    </EntityImportWizard>
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import EntityImportWizard from '@/views/admin/data-transfer/EntityImportWizard.vue';
import VMultiselect from '@/components/base/VMultiselect.vue';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';
import { useWarehouseStore } from '@/stores/admin/inventory/warehouse.js';

defineEmits(['imported']);

const store = useDataTransferStore();
const warehouseStore = useWarehouseStore();
const { stockLocationOptionsTree: warehouseOptionsTree } = storeToRefs(warehouseStore);

const wizardRef = ref(null);
const warehouseId = ref('');

const openingStockFields = [
    'product_code', 'sku', 'barcode', 'warehouse', 'quantity', 'rate',
    'batch_no', 'expiry_date', 'remarks',
];

const uploadOptions = computed(() => (warehouseId.value ? { warehouse_id: warehouseId.value } : {}));

const downloadWorksheet = (format) => store.downloadOpeningStockWorksheet(format);

const show = () => {
    warehouseId.value = '';
    warehouseStore.getWarehouses();
    wizardRef.value?.show();
};

defineExpose({ show });
</script>
