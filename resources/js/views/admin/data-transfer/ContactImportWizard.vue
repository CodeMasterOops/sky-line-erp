<template>
    <EntityImportWizard
        ref="wizardRef"
        entity-type="party"
        title="Import Contacts"
        entity-label="contact"
        :fields="contactFields"
        modal-id="contact-import-wizard"
        :template-download-fn="store.downloadContactTemplate"
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

const contactFields = [
    'type', 'name', 'code', 'phone', 'email', 'pan', 'address', 'credit_limit', 'is_active',
];

const show = () => wizardRef.value?.show();

defineExpose({ show });
</script>
