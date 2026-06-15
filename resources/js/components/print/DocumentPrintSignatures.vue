<template>
    <div class="form-document-signatures">
        <div
            v-for="(label, index) in labels"
            :key="index"
            class="form-document-signature-cell"
        >
            <div class="form-document-signature-line"></div>
            <p class="form-document-signature-label mb-0">{{ label }}</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    labels: {
        type: Array,
        default: () => ['Prepared By', 'Checked By', 'Approved By'],
    },
});
</script>
