<template>
    <div :id="areaId" class="form-document-print form-document-print-area card">
        <div class="card-body border-0">
            <slot />
        </div>
    </div>
</template>

<script setup>
import {onMounted} from 'vue';
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
});
</script>
