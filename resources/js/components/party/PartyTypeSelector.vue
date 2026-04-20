<template>
    <div class="col-12">
        <label class="form-label">
            Party type
            <span class="text-danger ms-1">*</span>
        </label>
        <p class="text-muted small mb-3">
            Choose whether this party is a customer you sell to, a supplier you buy from, or a sales lead.
        </p>
        <div class="row g-2">
            <div v-for="opt in options" :key="opt.id" class="col-12 col-md-4">
                <input
                    type="radio"
                    class="visually-hidden"
                    :id="`${idPrefix}-type-${opt.id}`"
                    :name="`${idPrefix}-party-type`"
                    :value="opt.id"
                    v-model="model"
                    @change="$emit('change')"
                >
                <label
                    :for="`${idPrefix}-type-${opt.id}`"
                    class="w-100 text-start p-3 rounded-3 border bg-white product-pricing-card mb-0"
                    :class="{ 'border-primary border-2 shadow-sm': model === opt.id }"
                >
                    <div class="fw-semibold text-dark">
                        <i :class="[opt.icon, 'me-1 text-primary']"></i>
                        {{ opt.name }}
                    </div>
                    <div class="small text-muted mt-1 mb-0">
                        {{ opt.description }}
                    </div>
                </label>
            </div>
        </div>
        <div v-if="error" class="text-danger small mt-1">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
defineProps({
    idPrefix: {
        type: String,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
});

defineEmits(['change']);

const model = defineModel({ type: String, required: true });

const options = [
    {
        id: 'customer',
        name: 'Customer',
        description: 'People or businesses you sell goods or services to.',
        icon: 'ti ti-user',
    },
    {
        id: 'supplier',
        name: 'Supplier',
        description: 'Vendors you purchase inventory or materials from.',
        icon: 'ti ti-truck',
    },
    {
        id: 'lead',
        name: 'Lead',
        description: 'Prospects you are nurturing before they become customers.',
        icon: 'ti ti-flag',
    },
];
</script>

<style scoped>
.product-pricing-card {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    cursor: pointer;
}

.product-pricing-card:hover {
    border-color: var(--bs-primary) !important;
}
</style>
