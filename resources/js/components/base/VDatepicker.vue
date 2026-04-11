<template>
    <label v-if="label" :for="id" class="form-label">{{ label }}</label>
    <div class="input-group">
        <flat-pickr
            v-if="dateType==='en'"
            :config="config"
            v-model="ad_date"
            @on-change="updateAdDateValue"
            :placeholder="placeholder || label"
            :disabled="disabled"
            :class="[inputClass, { 'is-invalid': error }]"
        />
        <input
            v-else
            :id="id"
            ref="neDateElement"
            :disabled="disabled"
            readonly
            :placeholder="placeholder ?? label"
            v-model="nep_date"
            v-bind:class="[inputClass, { 'is-invalid': error }]"
        />
        <span v-if="showSwitcher" class="input-group-text m-0 p-0 bg-light date-switcher">
            <input :disabled="disabled" type="checkbox" :id="'switch'+id" data-switch="success" @change="updateDateType"
                   :checked="dateType==='ne'">
            <label :for="'switch'+id" data-on-label="NP" data-off-label="EN" class="mb-0 d-block"></label>
        </span>
    </div>
    <p v-if="error" class="text-danger">
        {{ error }}
    </p>
</template>

<script setup>
import {onMounted, ref, watch} from "vue";
import {useDateHelper} from "@/composables/dateHelper";
import flatPickr from 'vue-flatpickr-component';

const emit = defineEmits(['validate'])

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
    defaultDate: {
        default: 'ne'
    },
    showSwitcher: {
        type: Boolean,
        default: false
    },
    disableAfter: {
        type: String
    },
    disableBefore: {
        type: String
    },
    disableDates: {
        type: Array
    }
})

const {adToBs, bsToAd} = useDateHelper();

const dateType = ref(props.defaultDate)
const neDateElement = ref('');

const form_date = defineModel();
const nep_date = ref('');
const ad_date = ref('');

onMounted(() => {
    setDate();
});

watch(() => neDateElement.value, (e) => {
    if (e) {
        neDateElement.value.NepaliDatePicker({
            dateFormat: "YYYY-MM-DD",
            //language: 'english',
            value: nep_date.value,
            maxDate: props.disableAfter ? adToBs(props.disableAfter) : '',
            minDate: props.disableBefore ? adToBs(props.disableBefore) : '',
            disableDates: props.disableDates?.length ? props.disableDates : [],
            onSelect: function (date) {
                form_date.value = bsToAd(date.value);
                nep_date.value = date.value;
                emit("validate")
            },
        });
    }
})

const config = ref({
    dateFormat: 'Y-m-d',
    maxDate: props.disableAfter,
    minDate: props.disableBefore,
});

const updateAdDateValue = (selectedDates, dateStr) => {
    emit('update:modelValue', dateStr)
    emit('validate');
}

const updateDateType = () => {
    dateType.value = dateType.value === 'en' ? 'ne' : 'en';
}

watch(() => form_date.value, () => {
    setDate();
})

const setDate = () => {
    if (form_date.value) {
        nep_date.value = adToBs(form_date.value);
        ad_date.value = form_date.value;
    } else {
        nep_date.value = '';
        ad_date.value = '';
    }
}
</script>
