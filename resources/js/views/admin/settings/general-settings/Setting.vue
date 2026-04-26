<template>
    <div>
        <PageHeader
            title="Setting"
            subtitle="Manage your company profile, address, and defaults"
            @refresh="setSettingData(true)"
        />
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar></settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-header">
                        <h4 class="fs-18 fw-bold">Company &amp; preferences</h4>
                    </div>
                    <VLoader v-if="setting.loading" loader-type="progress"/>
                    <div v-show="!setting.loading" class="card-body">
                        <form @submit.prevent="updateSetting">
                            <div class="card-title-head">
                                <h6 class="fs-16 fw-bold mb-3">
                                    <span class="fs-16 me-2"><i class="ti ti-building"></i></span>
                                    Basic information
                                </h6>
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <div class="profile-pic-upload settings-logo-row d-flex flex-wrap align-items-center">
                                        <div
                                            class="profile-pic settings-logo-preview d-flex align-items-center justify-content-center text-center flex-shrink-0"
                                            :class="{'p-0': logoBoxSrc}"
                                            role="button"
                                            tabindex="0"
                                            @click="openLogoPicker"
                                            @keydown.enter.prevent="openLogoPicker"
                                        >
                                            <img
                                                v-if="logoBoxSrc"
                                                :src="logoBoxSrc"
                                                alt="Company logo"
                                                class="d-block w-100 h-100 object-fit-contain rounded-1 p-1"
                                            />
                                            <span v-else>
                                                <i class="ti ti-circle-plus mb-0 fs-14 d-block"></i>
                                                <span class="d-block fs-12 mt-1">Add Image</span>
                                            </span>
                                        </div>
                                        <div
                                            class="new-employee-field settings-logo-column flex-grow-1 min-w-0 d-flex flex-column justify-content-center"
                                        >
                                            <VFileUpload
                                                id="logo"
                                                v-model="form.logo"
                                                :default-photo="setting.data.logo_url"
                                                :hide-image-preview="true"
                                                :button-only="true"
                                                image-height="80px"
                                                :max-size="2"
                                                :mimes="['image/jpeg', 'image/jpg', 'image/png']"
                                                browse-button-class="btn btn-primary settings-logo-upload-btn"
                                            />
                                            <p class="form-text settings-logo-hint mt-2 mb-0">
                                                Upload an image below 2 MB, Accepted File format JPG, PNG
                                            </p>
                                        </div>
                                    </div>
                                </div>
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
                            </div>

                            <div class="card-title-head">
                                <h6 class="fs-16 fw-bold mb-3">
                                    <span class="fs-16 me-2"><i class="ti ti-map-pin"></i></span>
                                    Address information
                                </h6>
                            </div>
                            <p class="text-muted small mb-3">Location (optional). Select province through ward, then add street detail and postal code.</p>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <VSelect
                                        id="st_province"
                                        v-model="form.province_id"
                                        label="Province"
                                        :options="provinces"
                                    />
                                </div>
                                <div class="col-md-3">
                                    <VSelect
                                        id="st_district"
                                        v-model="form.district_id"
                                        :disabled="!form.province_id"
                                        label="District"
                                        :options="districtOptions"
                                    />
                                </div>
                                <div class="col-md-3">
                                    <VSelect
                                        id="st_palika"
                                        v-model="form.palika_id"
                                        :disabled="!form.district_id"
                                        label="Palika"
                                        :options="palikaOptions"
                                    />
                                </div>
                                <div class="col-md-3">
                                    <VSelect
                                        id="st_ward"
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
                            </div>

                            <div class="card-title-head">
                                <h6 class="fs-16 fw-bold mb-3">
                                    <span class="fs-16 me-2"><i class="ti ti-calculator"></i></span>
                                    Fiscal &amp; inventory
                                </h6>
                            </div>
                            <div class="row g-3 mb-4">
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
                                <div class="col-md-8">
                                    <VSelect
                                        id="inventory_costing_method"
                                        v-model="form.inventory_costing_method"
                                        :options="inventoryCostingOptions"
                                        label="Inventory costing"
                                        @validate="validateField('inventory_costing_method')"
                                        :error="errors.inventory_costing_method"
                                    />
                                    <p class="text-muted small mb-0 mt-1">
                                        All inventory issues and the cost of goods on approved sales use this method company-wide. It applies when receiving and issuing stock (bills, invoices, adjustments, transfers).
                                    </p>
                                </div>
                            </div>

                            <div class="text-end settings-bottom-btn">
                                <VButton :loading="isSubmitting"/>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import {computed, nextTick, onMounted, onUnmounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup.js';
import {storeToRefs} from 'pinia';
import {useSettingStore} from '@/stores/admin/setting.js';
import {useAdminSettingStore} from '@/stores/admin/admin-setting.js';
import {useAdminLocationStore} from '@/stores/admin/location.js';

const settingStore = useSettingStore();
const adminSettingStore = useAdminSettingStore();
const locationStore = useAdminLocationStore();
const {provinces} = storeToRefs(locationStore);
const {setting} = storeToRefs(settingStore);
const {fiscalYears} = storeToRefs(adminSettingStore);

const districtOptions = ref([]);
const palikaOptions = ref([]);
const wardOptions = ref([]);
const suppressLocCascade = ref(false);

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
    logo: '',
    fiscal_year_id: '',
    inventory_costing_method: 'fifo',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const logoBlobUrl = ref(null);

const logoBoxSrc = computed(() => {
    if (form.logo && form.logo instanceof File) {
        return logoBlobUrl.value;
    }
    return setting.value.data?.logo_url || null;
});

watch(
    () => form.logo,
    (f) => {
        if (logoBlobUrl.value) {
            URL.revokeObjectURL(logoBlobUrl.value);
            logoBlobUrl.value = null;
        }
        if (f && f instanceof File) {
            logoBlobUrl.value = URL.createObjectURL(f);
        }
    }
);

function openLogoPicker() {
    document.getElementById('logo')?.click();
}

onUnmounted(() => {
    if (logoBlobUrl.value) {
        URL.revokeObjectURL(logoBlobUrl.value);
    }
});

const validations = object({
    company_name: string().required('Company name is required.'),
    legal_name: string().required('Legal name is required.'),
    code: string().required('Code is required.'),
    pan: string().nullable(),
    phone: string().nullable(),
    landline: string().nullable(),
    website: string().nullable(),
    email: string().required('Email is required.').email('Invalid email format'),
    address: string().nullable(),
    postal_code: string().nullable(),
    fiscal_year_id: string().required('Fiscal year is required.'),
    inventory_costing_method: string().required('Inventory costing is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const inventoryCostingOptions = computed(() => {
    const raw = setting.value.data?.inventory_costing_method_options;
    if (!Array.isArray(raw) || !raw.length) {
        return [
            {id: 'fifo', name: 'FIFO (first in, first out)'},
            {id: 'weighted_average', name: 'Weighted average'},
        ];
    }
    return raw.map((o) => ({id: o.value, name: o.label}));
});

const setSettingData = async (refetch = false) => {
    await locationStore.loadProvinces();
    await settingStore.getSetting(refetch);
    const d = setting.value.data;
    suppressLocCascade.value = true;
    form.company_name = d.company_name ?? '';
    form.legal_name = d.legal_name ?? '';
    form.code = d.code ?? '';
    form.pan = d.pan ?? '';
    form.phone = d.phone ?? '';
    form.landline = d.landline ?? '';
    form.email = d.email ?? '';
    form.website = d.website ?? '';
    form.address = d.address ?? '';
    form.fiscal_year_id = d.fiscal_year_id != null && d.fiscal_year_id !== '' ? String(d.fiscal_year_id) : '';
    form.inventory_costing_method = d.inventory_costing_method || 'fifo';
    form.province_id = d.province_id != null && d.province_id !== '' ? String(d.province_id) : '';
    form.district_id = d.district_id != null && d.district_id !== '' ? String(d.district_id) : '';
    form.palika_id = d.palika_id != null && d.palika_id !== '' ? String(d.palika_id) : '';
    form.ward_id = d.ward_id != null && d.ward_id !== '' ? String(d.ward_id) : '';
    form.postal_code = d.postal_code ?? '';
    if (d.province_id) {
        await locationStore.loadDistricts(d.province_id);
        districtOptions.value = [...locationStore.districts];
    } else {
        districtOptions.value = [];
    }
    if (d.district_id) {
        await locationStore.loadPalikas(d.district_id);
        palikaOptions.value = [...locationStore.palikas];
    } else {
        palikaOptions.value = [];
    }
    if (d.palika_id) {
        await locationStore.loadWards(d.palika_id);
        wardOptions.value = [...locationStore.wards];
    } else {
        wardOptions.value = [];
    }
    await nextTick();
    suppressLocCascade.value = false;
};

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

onMounted(() => {
    setSettingData();
    adminSettingStore.getFiscalYears();
});

const updateSetting = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const formData = new FormData();
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
    Object.assign(form, {...initialState});
    errors.value = {};
};
</script>

