<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'super-admin.dashboard'}">
                    <i class="fa fa-home"> Home</i>
                </router-link>
            </li>
            <li class="breadcrumb-item active">Company</li>
        </ol>
    </div>
    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">Update Company</h5>
                <router-link :to="{name:'super-admin.company-list'}">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-list"> Company List</i>
                    </button>
                </router-link>
            </div>
            <div class="card-body">
                <VLoader v-if="company.loading" loader-type="progress"/>
                <form @submit.prevent="updateCompany(company.data.id)" class="g-3">
                    <fieldset class="border rounded-1 border-primary p-2 mb-3">
                        <legend class="text-primary">
                            <strong>Company Information</strong>
                        </legend>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <VInput
                                    id="company_name"
                                    v-model="form.company_name"
                                    label="Company Name"
                                    @validate="validateField('company_name')"
                                    :error="errors.company_name"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="legal_name"
                                    v-model="form.legal_name"
                                    label="Legal Name"
                                    @validate="validateField('legal_name')"
                                    :error="errors.legal_name"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="code"
                                    v-model="form.code"
                                    label="Code"
                                    @validate="validateField('code')"
                                    :error="errors.code"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="pan"
                                    v-model="form.pan"
                                    label="PAN"
                                    @validate="validateField('pan')"
                                    :error="errors.pan"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="phone"
                                    v-model="form.phone"
                                    label="Phone"
                                    @validate="validateField('phone')"
                                    :error="errors.phone"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="email"
                                    v-model="form.email"
                                    label="Email"
                                    @validate="validateField('email')"
                                    :error="errors.email"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="landline"
                                    v-model="form.landline"
                                    label="Landline"
                                    @validate="validateField('landline')"
                                    :error="errors.landline"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="website"
                                    v-model="form.website"
                                    label="Website"
                                    @validate="validateField('website')"
                                    :error="errors.website"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="address"
                                    v-model="form.address"
                                    label="Address"
                                    @validate="validateField('address')"
                                    :error="errors.address"
                                />
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="border rounded-1 border-primary p-2 mb-3">
                        <legend class="text-primary">
                            <strong>User Information</strong>
                        </legend>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <VInput
                                    id="user_name"
                                    v-model="form.user_name"
                                    label="User Name"
                                    @validate="validateField('user_name')"
                                    :error="errors.user_name"
                                />
                            </div>

                            <div class="col-md-4">
                                <VInput
                                    id="user_phone"
                                    v-model="form.user_phone"
                                    label="User Phone"
                                    @validate="validateField('user_phone')"
                                    :error="errors.user_phone"
                                />
                            </div>

                            <div class="col-md-4">
                                <VInput
                                    id="user_email"
                                    v-model="form.user_email"
                                    label="User Email"
                                    @validate="validateField('user_email')"
                                    :error="errors.user_email"
                                />
                            </div>
                        </div>
                    </fieldset>
                    <div class="col-12 text-end">
                        <VButton :loading="isSubmitting"/>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>


<script setup>
import {onMounted, reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useCompanyStore} from "@/stores/super-admin/company";
import {storeToRefs} from "pinia";
import {useRoute, useRouter} from "vue-router";

const companyStore = useCompanyStore();

const {company} = storeToRefs(companyStore);

const route = useRoute();
const router = useRouter();

const edit_company_id = ref(route.params.id);

const initialState = {
    company_name: '',
    legal_name: '',
    code: '',
    pan: '',
    phone: '',
    landline: '',
    email: '',
    website: '',
    address: '',
    user_name: '',
    user_phone: '',
    user_email: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false)

onMounted(async () => {
    if (edit_company_id.value) {
        await companyStore.getCompany(edit_company_id.value);
        Object.keys(form).forEach(key => {
            form[key] = company.value.data[key]
        })
    }
});

const validations = object({
    'company_name': string().required('Company name is required.'),
    'legal_name': string().required('Legal name is required.'),
    'code': string().required('Code is required.'),
    'pan': string().nullable(),
    'phone': string().nullable(),
    'landline': string().nullable(),
    'website': string().nullable(),
    'email': string().required('Email is required.').email('Invalid email format'),
    'user_name': string().required('User name is required.'),
    'user_phone': string().nullable(),
    'user_email': string().required('User email is required.').email('Invalid email format'),
    'address': string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateCompany = async (id) => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await companyStore.updateCompany(id, form);
            toast(res.status, res.data.message);
            resetForm();
            await router.push({name: 'super-admin.company-list'});
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
