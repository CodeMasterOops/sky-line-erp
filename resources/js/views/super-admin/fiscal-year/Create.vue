<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeCreateModal"
        modal-class="extra-medium-modal"
        title="Add Fiscal Year">
        <template #modal-body>
            <form @submit.prevent="storeFiscalYear" class="row g-3">
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
                        default-date="en"
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
                        default-date="en"
                        @validate="validateField('end_date')"
                        :error="errors.end_date"
                    />
                </div>
                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useFiscalYearStore} from '@/stores/super-admin/fiscal-year.js';

const fiscalYearStore = useFiscalYearStore();

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    year_name: '',
    year_code: '',
    start_date: '',
    end_date: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    year_name: string().required('Year name is required.'),
    year_code: string().required('Year code is required.'),
    start_date: string().required('Start date is required.'),
    end_date: string().required('End date is required.')
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeFiscalYear = async () => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await fiscalYearStore.storeFiscalYear(form);
            toast(res.status, res.data.message);
            closeCreateModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
}

const closeCreateModal = () => {
    resetForm();
    createModalOpened.value = false;
}

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
