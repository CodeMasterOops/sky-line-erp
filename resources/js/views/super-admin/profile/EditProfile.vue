<template>
    <form @submit.prevent="updateProfile">
        <div class="row">
            <div class="col-md-6 mb-2">
                <VInput
                    id="name"
                    v-model="form.name"
                    label="Name"
                    @validate="validateField('name')"
                    :error="errors.name"
                />
            </div>
            <div class="col-md-6 mb-2">
                <VInput
                    id="email"
                    v-model="form.email"
                    label="Email"
                    @validate="validateField('email')"
                    :error="errors.email"
                />
            </div>
            <div class="col-md-6 mb-2">
                <VInput
                    id="phone"
                    v-model="form.phone"
                    label="Phone"
                    @validate="validateField('phone')"
                    :error="errors.phone"
                />
            </div>
        </div>
        <div class="text-end mt-2">
            <VButton :loading="isSubmitting"/>
        </div>
    </form>
</template>

<script setup>
import {onMounted, reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {storeToRefs} from "pinia";
import {useSuperAdminProfileStore} from "@/stores/super-admin/profile.js";

const profileStore = useSuperAdminProfileStore();
const {profile} = storeToRefs(profileStore);

const initialState = {
    name: '',
    email: '',
    phone: '',
};
const form = reactive({...initialState});
const isSubmitting = ref(false)

onMounted(() => {
    fetchProfileData();
});

const fetchProfileData = async () => {
    await profileStore.getProfile();
    Object.keys(form).forEach(key => {
        form[key] = profile.value.data[key]
    })
}

const validations = object({
    'name': string().required('Name is required.'),
    'email': string().required('Email is required.'),
    'phone': string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);
const updateProfile = async () => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        const formData = new FormData()
        Object.keys(form).forEach(key => {
            formData.append(key, form[key] ?? '');
        });
        try {
            let res = await profileStore.updateProfile(formData);
            resetForm();
            await fetchProfileData();
            toast(res.status, res.data.message);
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



