<template>
    <div class="form-document-party-box mb-3">
        <p class="form-document-party-box-title mb-2">{{ title }}</p>
        <p v-if="name" class="form-document-party-name mb-1">{{ name }}</p>
        <p v-if="address" class="form-document-party-meta mb-1">{{ address }}</p>
        <p class="form-document-party-meta mb-0">
            <span v-if="phone">Phone: {{ phone }}</span>
            <span v-if="phone && pan"> · </span>
            <span v-if="pan">PAN: {{ pan }}</span>
        </p>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        default: 'Party',
    },
    name: {
        type: String,
        default: '',
    },
    address: {
        type: String,
        default: '',
    },
    phone: {
        type: String,
        default: '',
    },
    pan: {
        type: String,
        default: '',
    },
});
</script>
