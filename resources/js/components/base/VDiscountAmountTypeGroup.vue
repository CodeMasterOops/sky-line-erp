<template>
    <div>
        <div
            class="input-group"
            :class="extraGroupClass">
            <input
                :id="inputId || undefined"
                type="number"
                :class="[inputControlClass, {'is-invalid': !!error}]"
                :value="modelValue"
                :disabled="disabled"
                min="0"
                step="any"
                :aria-invalid="!!error"
                :aria-label="inputAriaLabel"
                @input="onInput"
                @blur="emit('blur', $event)"
            />
            <button
                class="btn btn-soft-primary dropdown-toggle v-discount-type-toggle"
                :class="[buttonSizeClass, {'v-discount-type-toggle--compact': compactToggle}]"
                type="button"
                data-bs-toggle="dropdown"
                aria-label="Discount type: fixed or percent">
                {{ lineDiscountTypeLabel(discountType) }}
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a
                        class="dropdown-item"
                        :class="{'active': discountType === 'fixed'}"
                        href="javascript:void(0);"
                        @click="setType('fixed')">{{ fixedLabel }}</a>
                </li>
                <li>
                    <a
                        class="dropdown-item"
                        :class="{'active': discountType === 'percent'}"
                        href="javascript:void(0);"
                        @click="setType('percent')">{{ percentLabel }}</a>
                </li>
            </ul>
        </div>
        <div v-if="error" class="invalid-feedback d-block small mb-0">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
import {computed} from 'vue';
import {lineDiscountTypeLabel} from '@/composables/purchaseOrderTotals.js';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    discountType: {
        type: String,
        default: 'fixed',
    },
    error: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    inputId: {
        type: String,
        default: undefined,
    },
    /**
     * `form-control` / `form-control-sm` and matching `btn` / `btn-sm`
     * (sizing on controls, not on `input-group-sm`, keeps row heights aligned).
     */
    size: {
        type: String,
        default: 'sm',
    },
    extraGroupClass: {
        type: String,
        default: '',
    },
    /** Smaller type toggle in dense table cells */
    compactToggle: {
        type: Boolean,
        default: false,
    },
    fixedLabel: {
        type: String,
        default: 'Fixed',
    },
    percentLabel: {
        type: String,
        default: '% (percent)',
    },
    inputAriaLabel: {
        type: String,
        default: 'Discount amount',
    },
});

const emit = defineEmits(['update:modelValue', 'update:discountType', 'blur']);

const inputControlClass = computed(() =>
    props.size === 'sm' ? 'form-control form-control-sm' : 'form-control'
);
const buttonSizeClass = computed(() => (props.size === 'sm' ? 'btn-sm' : ''));

// Do not bind `aria-expanded` on the toggle: Vue would reset it on re-renders and
// fight Bootstrap (noticeable with multiple line + order discount dropdowns on one form).

const onInput = (e) => {
    emit('update:modelValue', e.target.value);
};

const setType = (type) => {
    emit('update:discountType', type);
};
</script>
