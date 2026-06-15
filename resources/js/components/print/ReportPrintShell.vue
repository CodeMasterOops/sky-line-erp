<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
            <div />
            <DocumentPrintButton
                :target="`#${areaId}`"
                :title="reportTitle"
                :label="printLabel"
                :landscape="landscape"
            />
        </div>

        <div :id="areaId" class="card document-print-area border-0 shadow-none">
            <div class="card-body p-0">
                <DocumentPrintHeader
                    :document-title="reportTitle"
                    :document-date="subtitle"
                />

                <div v-if="$slots.filters" class="mb-3 text-muted small">
                    <slot name="filters" />
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>

<script setup>
import {onMounted} from 'vue';
import DocumentPrintHeader from '@/components/print/DocumentPrintHeader.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';

defineProps({
    areaId: {
        type: String,
        default: 'report-print-area',
    },
    reportTitle: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    printLabel: {
        type: String,
        default: 'Print Report',
    },
    landscape: {
        type: Boolean,
        default: false,
    },
});

const {ensureBranding} = useCompanyBranding();

onMounted(() => {
    ensureBranding();
});
</script>
