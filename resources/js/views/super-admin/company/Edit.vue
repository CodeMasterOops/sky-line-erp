<template>
    <div>
        <PageHeader
            title="Update Company"
            subtitle="Edit company and admin user details"
            @refresh="loadCompanyForm"
        >
            <template #actions>
                <router-link
                    :to="{name:'super-admin.company-list'}"
                    class="btn btn-outline-primary d-flex align-items-center"
                >
                    <i class="fa fa-list me-2"></i>
                    Company List
                </router-link>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card flex-fill mb-0">
                <div class="card-header">
                    <h4 class="fs-18 fw-bold">Company &amp; user</h4>
                </div>
                <VLoader v-if="company.loading" loader-type="progress"/>
                <div v-show="!company.loading" class="card-body">
                    <form @submit.prevent="updateCompany(company.data?.id)">
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
                            <div class="col-12 mt-1">
                                <p class="text-muted small mb-0">Location (optional)</p>
                            </div>
                            <div class="col-md-3">
                                <VSelect
                                    id="ed_province"
                                    v-model="form.province_id"
                                    label="Province"
                                    :options="provinces"
                                />
                            </div>
                            <div class="col-md-3">
                                <VSelect
                                    id="ed_district"
                                    v-model="form.district_id"
                                    :disabled="!form.province_id"
                                    label="District"
                                    :options="districtOptions"
                                />
                            </div>
                            <div class="col-md-3">
                                <VSelect
                                    id="ed_palika"
                                    v-model="form.palika_id"
                                    :disabled="!form.district_id"
                                    label="Palika"
                                    :options="palikaOptions"
                                />
                            </div>
                            <div class="col-md-3">
                                <VSelect
                                    id="ed_ward"
                                    v-model="form.ward_id"
                                    :disabled="!form.palika_id"
                                    label="Ward"
                                    :options="wardOptions"
                                    @onInput="onWardSelect"
                                />
                            </div>
                            <div class="col-md-4">
                                <VInput
                                    id="postal_code"
                                    v-model="form.postal_code"
                                    label="Postal code"
                                    @validate="validateField('postal_code')"
                                    :error="errors.postal_code"
                                />
                            </div>
                            <div class="col-md-8">
                                <VInput
                                    id="address"
                                    v-model="form.address"
                                    label="Street / building / detail"
                                    @validate="validateField('address')"
                                    :error="errors.address"
                                />
                            </div>
                            <div class="col-12 mt-1">
                                <p class="text-muted small mb-0">Admin user</p>
                            </div>
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
                            <div class="col-12 text-end">
                                <VButton :loading="isSubmitting"/>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>


<script setup>
import {nextTick, onMounted, reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useCompanyStore} from "@/stores/super-admin/company";
import {useLocationStore} from "@/stores/super-admin/location";
import {storeToRefs} from "pinia";
import {useRoute, useRouter} from "vue-router";

const companyStore = useCompanyStore();
const locationStore = useLocationStore();
const {provinces} = storeToRefs(locationStore);
const districtOptions = ref([]);
const palikaOptions = ref([]);
const wardOptions = ref([]);
const suppressLocCascade = ref(false);

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
    province_id: '',
    district_id: '',
    palika_id: '',
    ward_id: '',
    postal_code: '',
    user_name: '',
    user_phone: '',
    user_email: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

function buildPayload() {
    return {
        company_name: form.company_name,
        legal_name: form.legal_name,
        code: form.code,
        pan: form.pan,
        phone: form.phone,
        landline: form.landline,
        email: form.email,
        website: form.website,
        address: form.address,
        ward_id: form.ward_id ? Number(form.ward_id) : null,
        postal_code: form.postal_code || null,
        user_name: form.user_name,
        user_phone: form.user_phone,
        user_email: form.user_email
    };
}

function onWardSelect(wardId) {
    if (!wardId) {
        return;
    }
    const w = wardOptions.value.find((x) => String(x.id) === String(wardId));
    if (w?.postal_code) {
        form.postal_code = w.postal_code;
    }
}

watch(
    () => form.province_id,
    async (pid) => {
        if (suppressLocCascade.value) {
            return;
        }
        form.district_id = '';
        form.palika_id = '';
        form.ward_id = '';
        palikaOptions.value = [];
        wardOptions.value = [];
        if (!pid) {
            districtOptions.value = [];
            return;
        }
        await locationStore.loadDistricts(pid);
        districtOptions.value = [...locationStore.districts];
    }
);

watch(
    () => form.district_id,
    async (did) => {
        if (suppressLocCascade.value) {
            return;
        }
        form.palika_id = '';
        form.ward_id = '';
        wardOptions.value = [];
        if (!did) {
            palikaOptions.value = [];
            return;
        }
        await locationStore.loadPalikas(did);
        palikaOptions.value = [...locationStore.palikas];
    }
);

watch(
    () => form.palika_id,
    async (palikaId) => {
        if (suppressLocCascade.value) {
            return;
        }
        form.ward_id = '';
        if (!palikaId) {
            wardOptions.value = [];
            return;
        }
        await locationStore.loadWards(palikaId);
        wardOptions.value = [...locationStore.wards];
    }
);

const loadCompanyForm = async () => {
    if (!edit_company_id.value) {
        return;
    }
    await locationStore.loadProvinces();
    await companyStore.getCompany(edit_company_id.value);
    const c = company.value.data;
    if (c == null) {
        return;
    }
    suppressLocCascade.value = true;
    form.company_name = c.company_name ?? '';
    form.legal_name = c.legal_name ?? '';
    form.code = c.code ?? '';
    form.pan = c.pan ?? '';
    form.phone = c.phone ?? '';
    form.landline = c.landline ?? '';
    form.email = c.email ?? '';
    form.website = c.website ?? '';
    form.address = c.address ?? '';
    form.province_id = c.province_id != null && c.province_id !== '' ? String(c.province_id) : '';
    form.district_id = c.district_id != null && c.district_id !== '' ? String(c.district_id) : '';
    form.palika_id = c.palika_id != null && c.palika_id !== '' ? String(c.palika_id) : '';
    form.ward_id = c.ward_id != null && c.ward_id !== '' ? String(c.ward_id) : '';
    form.postal_code = c.postal_code ?? '';
    form.user_name = c.user_name ?? '';
    form.user_phone = c.user_phone ?? '';
    form.user_email = c.user_email ?? '';
    if (c.province_id) {
        await locationStore.loadDistricts(c.province_id);
        districtOptions.value = [...locationStore.districts];
    } else {
        districtOptions.value = [];
    }
    if (c.district_id) {
        await locationStore.loadPalikas(c.district_id);
        palikaOptions.value = [...locationStore.palikas];
    } else {
        palikaOptions.value = [];
    }
    if (c.palika_id) {
        await locationStore.loadWards(c.palika_id);
        wardOptions.value = [...locationStore.wards];
    } else {
        wardOptions.value = [];
    }
    await nextTick();
    suppressLocCascade.value = false;
};

onMounted(() => {
    loadCompanyForm();
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
    'address': string().nullable(),
    'postal_code': string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateCompany = async (id) => {
    if (!id) {
        return;
    }
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await companyStore.updateCompany(id, buildPayload());
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
    districtOptions.value = [];
    palikaOptions.value = [];
    wardOptions.value = [];
    errors.value = {};
}

</script>
