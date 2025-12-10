<template>
    <label v-if="label" :for="id" class="form-label">{{ label }}</label>
    <flat-pickr
        :config="config"
        v-model="date"
        @on-change="updateDateValue"
        :placeholder="placeholder || label"
        :disabled="disabled"
        :class="[inputClass, { 'is-invalid': error }]"
    />
    <div v-if="error" class="invalid-feedback">
        {{ error }}
    </div>
</template>

<script setup>
import {ref, watch} from 'vue';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';

const emit = defineEmits(['update:modelValue', 'validate']);

const props = defineProps({
    id: {
        type: String,
    },
    inputClass: {
        type: String,
        default: "form-control",
    },
    label: {
        type: String,
    },
    placeholder: {
        type: String,
    },
    error: {
        type: String,
        default: ''
    },
    disabled: {
        type: Boolean,
        default: false
    },
    readonly: {
        type: Boolean,
        default: false
    },
    disableAfter: {
        type: String
    },
    disableBefore: {
        type: String
    },
    mode: {
        default: 'single'
    }
});

const date = ref(props.mode === 'multiple' ? [] : '');

const form_date = defineModel();

const updateDateValue = (selectedDates, dateStr) => {
    const date = props.mode === 'multiple' ? dateStr.split(', ') : dateStr;
    emit('update:modelValue', date)
    emit('validate');
}

const config = ref({
    dateFormat: 'Y-m-d',
    maxDate: props.disableAfter,
    minDate: props.disableBefore,
    mode: props.mode,
});

const updatingDate = ref(false);

watch(() => form_date.value, () => {
    setDate();
})

const setDate = () => {
    if (!updatingDate.value) {
        date.value = form_date.value
        updatingDate.value = true;
    }
}
</script>
