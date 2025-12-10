<template>
    <form @submit.prevent="updatePassword">
        <div class="row">
            <div class="col-md-12 mb-2">
                <VInput
                    id="current_password"
                    input-type="password"
                    v-model="form.current_password"
                    label="Current Password"
                    @validate="validateField('current_password')"
                    :error="errors.current_password"
                />
            </div>
            <div class="col-md-6 mb-2">
                <VInput
                    id="password"
                    input-type="password"
                    v-model="form.password"
                    label="Password"
                    @validate="validateField('password')"
                    :error="errors.password"
                />
            </div>
            <div class="col-md-6 mb-2">
                <VInput
                    id="password_confirmation"
                    input-type="password"
                    v-model="form.password_confirmation"
                    label="Password Confirmation"
                    @validate="validateField('password_confirmation')"
                    :error="errors.password_confirmation"
                />
            </div>
        </div>
        <div class="text-end">
            <VButton :loading="isSubmitting"/>
        </div>
    </form>

</template>


<script setup>
import {reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useSuperAdminProfileStore} from "@/stores/super-admin/profile.js";

const profileStore = useSuperAdminProfileStore();

const initialState = {
    current_password: '',
    password: '',
    password_confirmation: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false)

const validations = object({
    'current_password': string().required('Current password is required.'),
    'password': string().required('Password is required.'),
    'password_confirmation': string().required('Password confirmation is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updatePassword = async () => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await profileStore.changePassword(form);
            toast(res.status, res.data.message);
            resetForm();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
}
const resetForm = () => {
    Object.assign(form, {...initialState});
    errors.value = {};
}
</script>



