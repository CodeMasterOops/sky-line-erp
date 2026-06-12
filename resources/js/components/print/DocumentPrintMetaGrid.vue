<template>
    <table class="form-document-meta-grid mb-3">
        <tbody>
            <tr v-for="(row, rowIndex) in rows" :key="rowIndex">
                <template v-for="(cell, cellIndex) in row" :key="cellIndex">
                    <td class="form-document-meta-label">{{ cell.label }}</td>
                    <td class="form-document-meta-value">{{ cell.value || '—' }}</td>
                </template>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
});
</script>
