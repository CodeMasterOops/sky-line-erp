<template>
    <button
        type="button"
        class="btn btn-outline-primary d-inline-flex align-items-center"
        :class="buttonClass"
        :disabled="printing"
        @click="handlePrint"
    >
        <span v-if="printing" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
        <i v-else class="ti ti-printer me-2" />
        {{ label }}
    </button>
</template>

<script setup>
import {useDocumentPrint} from '@/composables/useDocumentPrint.js';

const props = defineProps({
    target: {
        type: String,
        default: '#document-print-area',
    },
    title: {
        type: String,
        default: 'Document',
    },
    label: {
        type: String,
        default: 'Print',
    },
    buttonClass: {
        type: String,
        default: '',
    },
    landscape: {
        type: Boolean,
        default: false,
    },
});

const {printing, printElement} = useDocumentPrint();

const handlePrint = () => {
    printElement(props.target, props.title, {landscape: props.landscape});
};
</script>
