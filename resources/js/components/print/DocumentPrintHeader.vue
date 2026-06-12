<template>
    <div class="row justify-content-between align-items-start border-bottom mb-3">
        <div class="col-md-6">
            <div class="mb-3 document-print-logo d-flex">
                <template v-if="logoUrl">
                    <img :src="logoUrl" width="130" class="img-fluid" alt="logo">
                </template>
                <template v-else>
                    <img src="@/assets/images/logo.svg" width="130" class="img-fluid dark-logo" alt="logo">
                    <img src="@/assets/images/logo-white.svg" width="130" class="img-fluid white-logo" alt="logo">
                </template>
            </div>
            <p v-if="formattedAddress" class="mb-1">{{ formattedAddress }}</p>
            <p v-if="pan" class="mb-1"><strong>PAN:</strong> {{ pan }}</p>
            <p v-if="phone" class="mb-1">Tel: {{ phone }}<span v-if="landline"> / {{ landline }}</span></p>
            <p v-if="email" class="mb-1">Email: {{ email }}</p>
            <p v-if="website" class="mb-0">Web: {{ website }}</p>
        </div>
        <div class="col-md-6">
            <div class="text-md-end mb-3">
                <h5 v-if="documentTitle" class="text-gray mb-1">{{ documentTitle }}</h5>
                <p v-if="documentNo" class="mb-1 fw-medium">
                    No <span class="text-primary">#{{ documentNo }}</span>
                </p>
                <p v-if="documentDate" class="mb-1 fw-medium">
                    Date: <span class="text-dark">{{ documentDate }}</span>
                </p>
                <slot name="header-meta" />
            </div>
        </div>
    </div>
</template>

<script setup>
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';

defineProps({
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

const {
    logoUrl,
    pan,
    phone,
    landline,
    email,
    website,
    formattedAddress,
} = useCompanyBranding();
</script>
