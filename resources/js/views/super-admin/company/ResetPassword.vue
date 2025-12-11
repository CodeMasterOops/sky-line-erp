<template>
    <VModal
        :show-modal="!!reset_company_password"
        @close-click="closeEditModal"
        title="Reset Password">
        <template #modal-body>
            <form @submit.prevent="resetPassword(reset_company_password)" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="password"
                        v-model="form.password"
                        input-type="password"
                        label="New Password"
                        @validate="validateField('password')"
                        :error="errors.password"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="password_confirmation"
                        input-type="password"
                        v-model="form.password_confirmation"
                        label="Confirm Password"
                        @validate="validateField('password_confirmation')"
                        :error="errors.password_confirmation"
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
import {reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import { useCompanyStore } from '@/stores/super-admin/company.js';

const companyStore = useCompanyStore();
const reset_company_password = defineModel('reset_password');

const initialState = {
    password: '',
    password_confirmation: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    password: string().required('Password is required.'),
    password_confirmation: string().required('Confirm password is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const resetPassword = async (id) => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await companyStore.resetPassword(id, form);
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
    reset_company_password.value = '';
}

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
