<template>
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
        <div class="search-set">
            <div class="search-input">
                <a href="javascript:void(0);" class="btn-searchset">
                    <i class="ti ti-search fs-14 feather-search"></i>
                </a>
                <input
                    type="search"
                    :value="modelValue"
                    class="form-control form-control-sm"
                    :placeholder="placeholder"
                    @input="onInput"
                >
            </div>
        </div>

        <div v-if="hasFilters || isFiltered" class="d-flex align-items-center gap-2 flex-wrap">
            <slot name="filters" />

            <button
                v-if="isFiltered"
                class="btn btn-sm btn-outline-secondary"
                @click="$emit('reset')"
            >
                <i class="ti ti-x me-1"></i> Clear
            </button>
        </div>
    </div>
</template>

<script setup>
import { useSlots, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search...',
    },
    isFiltered: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'search', 'reset']);

const slots = useSlots();
const hasFilters = computed(() => !!slots.filters);

function onInput(e) {
    emit('update:modelValue', e.target.value);
    emit('search', e.target.value);
}
</script>
