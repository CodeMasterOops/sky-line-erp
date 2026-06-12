<template>
    <div class="form-document-header">
        <div class="form-document-company-strip text-center">
            <div v-if="logoUrl" class="form-document-logo document-print-logo mb-2">
                <img :src="logoUrl" alt="Company logo" class="form-document-logo-image img-fluid">
            </div>
            <h5 v-if="displayName" class="form-document-company-name mb-1">{{ displayName }}</h5>
            <p v-if="formattedAddress" class="form-document-company-meta mb-1">{{ formattedAddress }}</p>
            <p class="form-document-company-meta mb-0">
                <span v-if="pan">PAN: {{ pan }}</span>
                <span v-if="pan && phone"> · </span>
                <span v-if="phone">Tel: {{ phone }}</span>
            </p>
        </div>

        <div class="form-document-title-box">
            <h4 class="form-document-title mb-0">{{ documentTitle }}</h4>
        </div>
    </div>
</template>

<script setup>
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';

defineProps({
    documentTitle: {
        type: String,
        required: true,
    },
});

const {
    logoUrl,
    displayName,
    formattedAddress,
    pan,
    phone,
} = useCompanyBranding();
</script>
