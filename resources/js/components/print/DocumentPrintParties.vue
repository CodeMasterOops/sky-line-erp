<template>
    <div class="col-md-6">
        <p class="text-dark mb-2 fw-semibold">From</p>
        <div>
            <h4 class="mb-1">{{ displayName }}</h4>
            <p v-if="formattedAddress" class="mb-1">{{ formattedAddress }}</p>
            <p v-if="pan" class="mb-1">PAN: {{ pan }}</p>
            <p v-if="phone" class="mb-1">Phone: {{ phone }}</p>
            <p v-if="email" class="mb-0">Email: {{ email }}</p>
        </div>
    </div>
    <div class="col-md-6">
        <p class="text-dark mb-2 fw-semibold">To</p>
        <div>
            <h4 class="mb-1">{{ partyName || '—' }}</h4>
            <p v-if="partyAddress" class="mb-1">{{ partyAddress }}</p>
            <p v-if="partyPhone" class="mb-0">Phone: {{ partyPhone }}</p>
        </div>
    </div>
</template>

<script setup>
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';

defineProps({
    partyName: {
        type: String,
        default: '',
    },
    partyAddress: {
        type: String,
        default: '',
    },
    partyPhone: {
        type: String,
        default: '',
    },
});

const {
    displayName,
    formattedAddress,
    pan,
    phone,
    email,
} = useCompanyBranding();
</script>
