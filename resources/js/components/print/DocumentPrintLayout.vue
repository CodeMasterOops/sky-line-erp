<template>
    <div :id="areaId" class="card document-print-area">
        <div class="card-body">
            <DocumentPrintHeader
                :document-title="documentTitle"
                :document-no="documentNo"
                :document-date="documentDate"
            >
                <template v-if="$slots['header-meta']" #header-meta>
                    <slot name="header-meta" />
                </template>
            </DocumentPrintHeader>

            <div v-if="$slots.parties" class="row border-bottom mb-3">
                <slot name="parties" />
            </div>

            <div v-if="$slots.body" class="mb-3">
                <slot name="body" />
            </div>

            <div v-if="$slots.totals" class="row border-bottom mb-3">
                <slot name="totals" />
            </div>

            <div v-if="$slots.footer">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<script setup>
import {onMounted} from 'vue';
import DocumentPrintHeader from '@/components/print/DocumentPrintHeader.vue';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';

const {ensureBranding} = useCompanyBranding();

onMounted(() => {
    ensureBranding();
});

defineProps({
    areaId: {
        type: String,
        default: 'document-print-area',
    },
    documentTitle: {
        type: String,
        default: '',
    },
    documentNo: {
        type: String,
        default: '',
    },
    documentDate: {
        type: String,
        default: '',
    },
});
</script>
