<template>
    <div>
        <label v-if="label" class="form-label">
            {{ label }}
            <VRequiredMark v-if="required" />
        </label>
        <select
            class="form-select form-select-sm"
            :class="{ 'is-invalid': error }"
            :value="modelValue"
            :disabled="disabled || !productVariantId || !warehouseId"
            @change="$emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)"
        >
            <option value="">— Select Batch —</option>
            <option
                v-for="batch in batches"
                :key="batch.id"
                :value="batch.id"
                :disabled="batch.status === 'expired'"
            >
                {{ batch.batch_no }}
                <template v-if="batch.expiry_date"> · Exp: {{ batch.expiry_date }}</template>
                · Qty: {{ batch.remaining_qty }}
                <template v-if="batch.status !== 'active'"> ({{ batch.status }})</template>
            </option>
        </select>
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-if="expiryWarning" class="form-text text-warning">
            <i class="ti ti-alert-triangle me-1"></i>{{ expiryWarning }}
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const props = defineProps({
    modelValue: { type: Number, default: null },
    productVariantId: { type: [Number, String], default: null },
    warehouseId: { type: [Number, String], default: null },
    label: { type: String, default: 'Batch' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const batches = ref([]);

const expiryWarning = computed(() => {
    if (!props.modelValue || !batches.value.length) return null;
    const selected = batches.value.find((b) => b.id === props.modelValue);
    if (!selected?.expiry_date) return null;
    const diff = Math.ceil((new Date(selected.expiry_date) - new Date()) / 86400000);
    if (diff < 0) return `Batch ${selected.batch_no} has already expired.`;
    if (diff <= 30) return `Batch ${selected.batch_no} expires in ${diff} day(s).`;
    return null;
});

async function loadBatches() {
    if (!props.productVariantId || !props.warehouseId) {
        batches.value = [];
        return;
    }
    try {
        const { data } = await apiAdmin(
            `batch/fefo?product_variant_id=${props.productVariantId}&warehouse_id=${props.warehouseId}`,
        );
        batches.value = data.data ?? [];

        // Auto-select the first active FEFO batch if nothing is selected yet
        if (!props.modelValue && batches.value.length) {
            emit('update:modelValue', batches.value[0].id);
        }
    } catch {
        batches.value = [];
    }
}

watch(
    () => [props.productVariantId, props.warehouseId],
    ([newVariant, newWarehouse], [oldVariant, oldWarehouse]) => {
        if (newVariant !== oldVariant || newWarehouse !== oldWarehouse) {
            emit('update:modelValue', null);
            loadBatches();
        }
    },
    { immediate: true },
);
</script>
