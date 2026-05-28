<template>
    <div
        class="v-discount-amount-type-group"
        :class="{
            'v-discount-amount-type-group--toggle': selectorMode === 'toggle',
            'v-discount-amount-type-group--compact': compactToggle,
        }">
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
            <template v-if="selectorMode === 'dropdown'">
                <button
                    class="btn btn-soft-primary dropdown-toggle v-discount-type-toggle"
                    :class="[buttonSizeClass, {'v-discount-type-toggle--compact': compactToggle}]"
                    type="button"
                    data-bs-toggle="dropdown"
                    :disabled="disabled"
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
            </template>
            <div
                v-else
                class="btn-group v-discount-type-toggle"
                :class="[buttonSizeClass, {'v-discount-type-toggle--compact': compactToggle}]"
                role="group"
                aria-label="Discount type">
                <button
                    type="button"
                    class="btn btn-soft-primary v-discount-type-toggle__btn"
                    :class="{'active': discountType === 'fixed'}"
                    :disabled="disabled"
                    :aria-pressed="discountType === 'fixed'"
                    :aria-label="fixedLabel"
                    :title="fixedLabel"
                    @click="setType('fixed')">
                    <i class="ti ti-currency-rupee v-discount-type-toggle__icon" aria-hidden="true"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-soft-primary v-discount-type-toggle__btn"
                    :class="{'active': discountType === 'percent'}"
                    :disabled="disabled"
                    :aria-pressed="discountType === 'percent'"
                    :aria-label="percentLabel"
                    :title="percentLabel"
                    @click="setType('percent')">
                    <i class="ti ti-percentage v-discount-type-toggle__icon" aria-hidden="true"></i>
                </button>
            </div>
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
     * `dropdown` — Bootstrap menu (default, used on purchase/sales forms).
     * `toggle` — segmented Fixed / % buttons (POS and future rollout).
     */
    selectorMode: {
        type: String,
        default: 'dropdown',
        validator: (v) => ['dropdown', 'toggle'].includes(v),
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

// Do not bind `aria-expanded` on the dropdown toggle: Vue would reset it on re-renders and
// fight Bootstrap (noticeable with multiple line + order discount dropdowns on one form).

const onInput = (e) => {
    emit('update:modelValue', e.target.value);
};

const setType = (type) => {
    if (type === props.discountType) {
        return;
    }
    emit('update:discountType', type);
};
</script>

<style scoped>
.v-discount-amount-type-group--toggle .input-group {
    flex-wrap: nowrap;
}

.v-discount-amount-type-group--toggle .v-discount-type-toggle {
    flex-shrink: 0;
}

.v-discount-amount-type-group--toggle .v-discount-type-toggle__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    padding-left: 0.35rem;
    padding-right: 0.35rem;
    line-height: 1;
}

.v-discount-amount-type-group--toggle .v-discount-type-toggle__icon {
    font-size: 1rem;
    line-height: 1;
}

.v-discount-amount-type-group--toggle .v-discount-type-toggle__btn.active {
    background-color: var(--bs-primary) !important;
    border-color: var(--bs-primary) !important;
    color: var(--bs-white) !important;
    box-shadow: 0 2px 8px rgba(var(--bs-primary-rgb), 0.35);
    z-index: 1;
}

.v-discount-amount-type-group--toggle .v-discount-type-toggle__btn.active .v-discount-type-toggle__icon {
    color: inherit;
}

.v-discount-amount-type-group--toggle .v-discount-type-toggle__btn:not(.active):hover:not(:disabled) {
    background-color: rgba(var(--bs-primary-rgb), 0.18) !important;
    border-color: var(--bs-primary) !important;
    color: var(--bs-primary) !important;
}

.v-discount-amount-type-group--compact .v-discount-type-toggle__btn {
    min-width: 1.65rem;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}

.v-discount-amount-type-group--compact .v-discount-type-toggle__icon {
    font-size: 0.875rem;
}

.v-discount-amount-type-group--toggle .input-group > .form-control:not(:last-child) {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.v-discount-amount-type-group--toggle .v-discount-type-toggle .v-discount-type-toggle__btn:first-child {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
</style>
