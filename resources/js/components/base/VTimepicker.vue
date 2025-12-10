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
import {onMounted, ref} from 'vue';
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

    modelValue: {
        type: [String, Number],
        required: true,
    },
});

const date = ref('');

onMounted(() => {
    if (props.modelValue) {
        date.value = props.modelValue;
    }
})

const updateDateValue = (selectedDates, dateStr) => {
    emit('update:modelValue', dateStr)
    emit('validate');
}

const config = ref({
    enableTime: true,
    noCalendar: true,
    dateFormat: "H:i",
});
</script>
