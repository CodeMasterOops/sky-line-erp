<template>
    <button type="button" @click="handlePrint" class="btn btn-sm btn-outline-primary">
        <span
            v-if="printing"
            class="spinner-border spinner-border-sm"
            role="status"
            aria-hidden="true"
        />
        <i v-else class="fa fa-print" />
        {{ buttonLabel }}
    </button>
</template>

<script setup>
import {useDocumentPrint} from '@/composables/useDocumentPrint.js';

const props = defineProps({
    title: {
        type: String,
        default: 'Document',
    },
    target: {
        type: String,
        default: '#document-print-area',
    },
    buttonLabel: {
        type: String,
        default: 'PRINT',
    },
});

const {printing, printElement} = useDocumentPrint();

const handlePrint = () => {
    printElement(props.target, props.title);
};
</script>
