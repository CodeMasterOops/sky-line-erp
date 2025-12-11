<template>
    <VModal
        :show-modal="!!edit_fiscal_year_id"
        @close-click="closeEditModal"
        modal-class="extra-medium-modal"
        title="Edit Fiscal year">
        <template #modal-body>
            <VLoader v-if="fiscalYear.loading" loader-type="progress"/>
            <form @submit.prevent="updateFiscalYear(fiscalYear.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="year_name"
                        v-model="form.year_name"
                        label="Year Name"
                        @validate="validateField('year_name')"
                        :error="errors.year_name"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="year_code"
                        v-model="form.year_code"
                        label="Code"
                        @validate="validateField('year_code')"
                        :error="errors.year_code"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="start_date"
                        v-model="form.start_date"
                        label="Start Date"
                        :show-switcher="false"
                        @validate="validateField('start_date')"
                        :error="errors.start_date"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="end_date"
                        v-model="form.end_date"
                        label="End Date"
                        :show-switcher="false"
                        @validate="validateField('end_date')"
                        :error="errors.end_date"
                    />
                </div>
                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {storeToRefs} from "pinia";
import { useFiscalYearStore } from '@/stores/super-admin/fiscal-year.js';

const fiscalYearStore = useFiscalYearStore();

const edit_fiscal_year_id = defineModel('fiscal_year_id');

const {fiscalYear} = storeToRefs(fiscalYearStore);

const initialState = {
    year_name: '',
    year_code: '',
    start_date: '',
    end_date: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_fiscal_year_id.value, async (id) => {
    if (id) {
        await fiscalYearStore.getFiscalYear(id);
        Object.keys(form).forEach(key => {
            form[key] = fiscalYear.value.data[key] ?? '';
        })
    }
})

const validations = object({
    year_name: string().required('Year name is required.'),
    year_code: string().required('Year code is required.'),
    start_date: string().required('Start date is required.'),
    end_date: string().required('End date is required.')
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateFiscalYear = async (id) => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await fiscalYearStore.updateFiscalYear(id, form);
            toast(res.status, res.data.message);
            closeEditModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
}

const closeEditModal = () => {
    resetForm();
    edit_fiscal_year_id.value = '';
}

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
