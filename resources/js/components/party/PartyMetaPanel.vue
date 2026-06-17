<template>
    <div v-if="party" class="party-meta-panel rounded border bg-light p-3 mt-2">
        <div class="row g-2 align-items-start">
            <div class="col-sm-4 col-md-3">
                <div class="text-muted small text-uppercase ls-1">{{ panHeading }}</div>
                <div v-if="panDisplay" class="fw-medium">{{ panDisplay }}</div>
                <div v-else class="fst-italic text-muted">{{ emptyPanText }}</div>
            </div>
            <div class="col-sm-4 col-md-4">
                <div class="text-muted small text-uppercase ls-1">Address</div>
                <div v-if="addressDisplay" class="fw-medium">{{ addressDisplay }}</div>
                <div v-else class="fst-italic text-muted">{{ emptyAddressText }}</div>
            </div>
            <div v-if="creditLimitDisplay" class="col-sm-4 col-md-3">
                <div class="text-muted small text-uppercase ls-1">Credit Limit</div>
                <div class="fw-medium">{{ creditLimitDisplay }}</div>
            </div>
            <div class="col-sm-4 col-md-2 text-sm-end ms-auto">
                <router-link
                    v-if="detailRoute"
                    :to="detailRoute"
                    class="text-primary text-decoration-none small">
                    View Details
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import {computed} from 'vue';

const props = defineProps({
    party: {
        type: Object,
        default: null,
    },
    panHeading: {
        type: String,
        default: 'PAN Number',
    },
    emptyPanText: {
        type: String,
        default: 'Not available',
    },
    emptyAddressText: {
        type: String,
        default: 'Not available',
    },
});

const panDisplay = computed(() => {
    const p = props.party?.pan;
    if (p == null || p === '') {
        return '';
    }
    return String(p).trim();
});

const creditLimitDisplay = computed(() => {
    const cl = props.party?.credit_limit;
    if (cl == null || cl === '' || Number(cl) <= 0) {
        return '';
    }
    return Number(cl).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
});

const addressDisplay = computed(() => {
    const a = props.party?.address;
    if (a == null || a === '') {
        return '';
    }
    return String(a).trim();
});

const detailRoute = computed(() => {
    const id = props.party?.id;
    if (id == null || id === '') {
        return null;
    }
    return {
        name: 'admin.party-list',
        query: { open_party: String(id) },
    };
});
</script>

<style scoped>
.party-meta-panel {
    --bs-border-color: rgba(13, 110, 253, 0.12);
}
</style>
