<template>
    <VMultiselect
        :id="id"
        :model-value="modelValue"
        :options="normalizedOptions"
        :label="label"
        :placeholder="placeholder ?? label"
        :error="error"
        :invalid="!!error"
        :value-prop="valueProp"
        :name-prop="nameProp"
        :disabled="disabled"
        :required="required"
        :size="size"
        mode="single"
        @update:model-value="onUpdate"
        @validate="emit('validate')"
    />
</template>

<script setup>
import { computed } from 'vue';
import VMultiselect from '@/components/base/VMultiselect.vue';

const emit = defineEmits(['update:modelValue', 'validate', 'onInput']);

const props = defineProps({
    id: {
        type: String,
    },
    selectClass: {
        type: String,
        default: 'form-select',
    },
    label: {
        type: String,
    },
    placeholder: {
        type: String,
    },
    error: {
        type: String,
        default: '',
    },
    modelValue: {
        type: [String, Number],
        required: true,
    },
    options: {
        required: true,
        type: Array,
    },
    valueProp: {
        type: String,
        default: 'id',
    },
    nameProp: {
        type: String,
        default: 'name',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
});

const size = computed(() => (props.selectClass.includes('form-select-sm') ? 'sm' : 'default'));

const onUpdate = (value) => {
    const next = value ?? '';
    emit('update:modelValue', next);
    emit('onInput', next);
    emit('validate');
};

const normalizedOptions = computed(() =>
    props.options.map((option) => {
        if (option !== null && typeof option === 'object') {
            return option;
        }

        return {
            [props.valueProp]: option,
            [props.nameProp]: String(option),
        };
    }),
);
</script>
