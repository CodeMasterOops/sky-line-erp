<template>
    <PageHeader title="Setting" subtitle="Manage your settings" @refresh="setSettingData()" />

    <section class="section">
        <div class="card">
            <VLoader v-if="setting.loading" loader-type="progress" />
            <div class="card-body">
                <form @submit.prevent="updateSetting">
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
                        <div class="col-md-4">
                            <VSelect
                                id="fiscal_year_id"
                                v-model="form.fiscal_year_id"
                                :options="fiscalYears.data"
                                name-prop="year_name"
                                label="Fiscal Year"
                                @validate="validateField('fiscal_year_id')"
                                :error="errors.fiscal_year_id"
                            />
                        </div>
                        <div class="col-md-4">
                            <VFileUpload
                                id="logo"
                                v-model="form.logo"
                                label="Logo"
                                :default-photo="setting.data.logo_url"
                            />
                        </div>
                        <div class="col-12 text-end">
                            <VButton :loading="isSubmitting" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { storeToRefs } from 'pinia';
import { useSettingStore } from '@/stores/admin/setting';
import { useAdminSettingStore } from '@/stores/admin/admin-setting.js';

const settingStore = useSettingStore();
const adminSettingStore = useAdminSettingStore();

onMounted(() => {
    setSettingData();
    adminSettingStore.getFiscalYears();
});

const setSettingData = async (refetch = false) => {
    await settingStore.getSetting(refetch);
    Object.keys(form).forEach(key => {
        form[key] = setting.value.data[key] || '';
    });
};

const { setting } = storeToRefs(settingStore);
const { fiscalYears } = storeToRefs(adminSettingStore);

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
    logo: '',
    fiscal_year_id: ''
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

const validations = object({
    company_name: string().required('Company name is required.'),
    legal_name: string().required('Legal name is required.'),
    code: string().required('Code is required.'),
    pan: string().nullable(),
    phone: string().nullable(),
    landline: string().nullable(),
    website: string().nullable(),
    email: string().required('Email is required.').email('Invalid email format'),
    address: string().nullable()
});

const { errors, validateField, validateForm } = useYup(form, validations);

const updateSetting = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const formData=new FormData();
            Object.keys(form).forEach(key => {
                formData.append(key, form[key] || '');
            });
            const res = await settingStore.updateSetting(formData);
            toast(res.status, res.data.message);
            resetForm();
            await setSettingData(true);
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const resetForm = () => {
    Object.assign(form, { ...initialState });
    errors.value = {};
};
</script>

