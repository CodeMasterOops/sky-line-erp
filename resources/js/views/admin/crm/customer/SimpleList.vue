<template>
    <div v-if="loading">
        <div v-for="n in 3" :key="n" class="placeholder-glow mb-2">
            <span class="placeholder col-5 me-2"></span><span class="placeholder col-3"></span>
        </div>
    </div>
    <p v-else-if="!rows.length" class="text-muted mb-0">{{ empty }}</p>
    <ul v-else class="list-group list-group-flush">
        <li
            v-for="row in rows"
            :key="row.id"
            class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3"
        >
            <div class="d-flex flex-column">
                <slot name="row" :row="row" />
            </div>
            <button
                v-if="deletable"
                type="button"
                class="btn btn-sm btn-link text-danger p-0"
                title="Delete"
                @click="$emit('delete', row.id)"
            >
                <i class="ti ti-trash"></i>
            </button>
        </li>
    </ul>
</template>

<script setup>
defineProps({
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    empty: { type: String, default: 'No records.' },
    deletable: { type: Boolean, default: false },
});

defineEmits(['delete']);
</script>
