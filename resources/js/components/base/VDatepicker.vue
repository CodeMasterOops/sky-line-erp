<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-1">
            <label v-if="label" :for="id" class="form-label mb-0">
                {{ label }}
                <VRequiredMark v-if="required" />
            </label>
            <div v-if="showSwitcher" class="btn-group ms-auto" role="group" aria-label="Date format">
                <button
                    type="button"
                    class="btn btn-xs py-0 px-2"
                    style="font-size: 0.7rem; line-height: 1.4;"
                    :class="dateType === 'en' ? 'btn-primary' : 'btn-outline-secondary'"
                    :disabled="disabled"
                    @click.prevent="setLocalMode('en')"
                >AD</button>
                <button
                    type="button"
                    class="btn btn-xs py-0 px-2"
                    style="font-size: 0.7rem; line-height: 1.4;"
                    :class="dateType === 'ne' ? 'btn-primary' : 'btn-outline-secondary'"
                    :disabled="disabled"
                    @click.prevent="setLocalMode('ne')"
                >BS</button>
            </div>
        </div>
        <flat-pickr
            v-if="dateType === 'en'"
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
        <div v-if="error" class="invalid-feedback d-block">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
import {onMounted, ref, watch} from "vue";
import {useDateHelper} from "@/composables/dateHelper";
import flatPickr from 'vue-flatpickr-component';
import VRequiredMark from '@/components/base/VRequiredMark.vue';
import {useDatePreferenceStore} from "@/stores/admin/datePreference.js";
import {storeToRefs} from "pinia";

const emit = defineEmits(['validate'])

const props = defineProps({
    id: {
        type: String,
    },
    inputClass: {
        type: String,
        default: "form-control",
    },
    inputType: {
        type: String,
        default: "date",
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
    showSwitcher: {
        type: Boolean,
        default: true
    },
    disableAfter: {
        type: String
    },
    disableBefore: {
        type: String
    },
    disableDates: {
        type: Array
    },
    required: {
        type: Boolean,
        default: false,
    },
})

const {adToBs, bsToAd} = useDateHelper();

const datePreferenceStore = useDatePreferenceStore();
const {mode: globalMode} = storeToRefs(datePreferenceStore);

const dateType = ref(globalMode.value);
const neDateElement = ref('');

const form_date = defineModel();
const nep_date = ref('');
const ad_date = ref('');

watch(globalMode, (m) => {
    dateType.value = m;
});

const setLocalMode = (mode) => {
    dateType.value = mode;
};

onMounted(() => {
    setDate();
});

watch(() => neDateElement.value, (e) => {
    if (e) {
        e.NepaliDatePicker({
            dateFormat: "YYYY-MM-DD",
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
