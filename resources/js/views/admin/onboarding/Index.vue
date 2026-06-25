<template>
    <main class="main-wrapper">
        <div class="account-content">
            <div class="login-wrapper login-new">
                <div class="row w-100 justify-content-center">
                    <div class="col-lg-8 col-xl-7">

                        <div class="login-content user-login">
                            <!-- Logo — same structure as Login/Register pages -->
                            <div class="login-logo">
                                <img :src="appLogo" alt="Sky ERP" />
                                <router-link :to="{ name: 'admin.dashboard' }" class="login-logo logo-white">
                                    <img :src="appLogoWhite" alt="Sky ERP" />
                                </router-link>
                            </div>

                            <!-- Step progress bar -->
                            <div class="d-flex align-items-flex-start mb-4" style="width: 100%;">
                                <template v-for="(stepLabel, i) in steps" :key="i">
                                    <!-- Step node: circle + label stacked -->
                                    <div class="d-flex flex-column align-items-center" style="flex: 0 0 auto; min-width: 96px;">
                                        <div
                                            class="rounded-circle d-flex align-items-center justify-content-center fw-semibold border"
                                            :class="stepCircleClass(i)"
                                            style="width: 38px; height: 38px; font-size: 14px; transition: background .2s, border-color .2s;"
                                        >
                                            <i v-if="currentStep > i" class="ti ti-check" style="font-size: 16px;"></i>
                                            <span v-else>{{ i + 1 }}</span>
                                        </div>
                                        <span
                                            class="mt-2 text-center"
                                            style="font-size: 11px; line-height: 1.3; max-width: 86px;"
                                            :class="currentStep === i ? 'text-primary fw-semibold' : 'text-muted'"
                                        >{{ stepLabel }}</span>
                                    </div>
                                    <!-- Connector line — margin-top: 19px = half of 38px circle to centre-align -->
                                    <div
                                        v-if="i < steps.length - 1"
                                        class="flex-grow-1"
                                        style="height: 2px; margin-top: 19px; border-radius: 1px;"
                                        :style="currentStep > i ? 'background: var(--bs-primary)' : 'background: #dee2e6'"
                                    ></div>
                                </template>
                            </div>

                            <!-- Card -->
                            <div class="card shadow-sm">
                                <div class="card-body p-4 p-lg-5">

                                    <!-- Loading skeleton -->
                                    <div v-if="isLoadingData" class="py-4 text-center text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Loading your company data...
                                    </div>

                                    <!-- Step 1: Company Details -->
                                    <template v-else-if="currentStep === 0">
                                        <div class="mb-4">
                                            <h4 class="fw-bold mb-1">Company Details</h4>
                                            <p class="text-muted small mb-0">Complete your company profile before you start.</p>
                                        </div>

                                        <div v-if="stepError" class="alert alert-danger py-2 mb-3">{{ stepError }}</div>

                                        <!-- Basic info section -->
                                        <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size: 11px; letter-spacing: .06em;">
                                            <i class="ti ti-building me-1"></i> Basic Information
                                        </h6>
                                        <div class="row g-3 mb-4">
                                            <div class="col-md-6">
                                                <VInput
                                                    id="company_name"
                                                    v-model="companyForm.company_name"
                                                    label="Company Name"
                                                    :disabled="true"
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <VInput
                                                    id="legal_name"
                                                    v-model="companyForm.legal_name"
                                                    label="Legal Name"
                                                    placeholder="e.g. Sky Technologies Pvt. Ltd."
                                                />
                                            </div>
                                            <div class="col-md-4">
                                                <VInput
                                                    id="phone"
                                                    v-model="companyForm.phone"
                                                    label="Phone"
                                                    :required="true"
                                                    :error="fieldErrors.phone?.[0]"
                                                    placeholder="+977-01-XXXXXXX"
                                                />
                                            </div>
                                            <div class="col-md-4">
                                                <VInput
                                                    id="pan"
                                                    v-model="companyForm.pan"
                                                    label="PAN / Reg. No."
                                                    :required="true"
                                                    :error="fieldErrors.pan?.[0]"
                                                    placeholder="XXXXXXXXX"
                                                />
                                            </div>
                                            <div class="col-md-4">
                                                <VInput
                                                    id="website"
                                                    v-model="companyForm.website"
                                                    label="Website"
                                                    placeholder="https://yourcompany.com"
                                                />
                                            </div>
                                        </div>

                                        <!-- Address section -->
                                        <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size: 11px; letter-spacing: .06em;">
                                            <i class="ti ti-map-pin me-1"></i> Address
                                        </h6>
                                        <div class="row g-3 mb-4">
                                            <div class="col-md-3">
                                                <VSelect
                                                    id="province_id"
                                                    v-model="companyForm.province_id"
                                                    label="Province"
                                                    :required="true"
                                                    :error="fieldErrors.province_id?.[0]"
                                                    :options="provinces"
                                                />
                                            </div>
                                            <div class="col-md-3">
                                                <VSelect
                                                    id="district_id"
                                                    v-model="companyForm.district_id"
                                                    label="District"
                                                    :required="true"
                                                    :error="fieldErrors.district_id?.[0]"
                                                    :disabled="!companyForm.province_id"
                                                    :options="districtOptions"
                                                />
                                            </div>
                                            <div class="col-md-3">
                                                <VSelect
                                                    id="palika_id"
                                                    v-model="companyForm.palika_id"
                                                    label="Palika"
                                                    :required="true"
                                                    :error="fieldErrors.palika_id?.[0]"
                                                    :disabled="!companyForm.district_id"
                                                    :options="palikaOptions"
                                                />
                                            </div>
                                            <div class="col-md-3">
                                                <VSelect
                                                    id="ward_id"
                                                    v-model="companyForm.ward_id"
                                                    label="Ward"
                                                    :required="true"
                                                    :error="fieldErrors.ward_id?.[0]"
                                                    :disabled="!companyForm.palika_id"
                                                    :options="wardOptions"
                                                    @onInput="onWardSelect"
                                                />
                                            </div>
                                            <div class="col-md-4">
                                                <VInput
                                                    id="postal_code"
                                                    v-model="companyForm.postal_code"
                                                    label="Postal Code"
                                                    placeholder="Optional"
                                                />
                                            </div>
                                            <div class="col-md-8">
                                                <VInput
                                                    id="address"
                                                    v-model="companyForm.address"
                                                    label="Street / Building / Detail"
                                                    :required="true"
                                                    :error="fieldErrors.address?.[0]"
                                                    placeholder="Street name, building no."
                                                />
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            <button type="button" class="btn btn-primary px-4" :disabled="isLoading" @click="submitCompanyStep">
                                                <span v-if="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                                                Save & Continue <i class="ti ti-arrow-right ms-1"></i>
                                            </button>
                                        </div>
                                    </template>

                                    <!-- Step 2: Branch (skippable) -->
                                    <template v-else-if="currentStep === 1">
                                        <div class="mb-4">
                                            <h4 class="fw-bold mb-1">Head Office Branch</h4>
                                            <p class="text-muted small mb-0">
                                                A default branch was created automatically. You can configure it now or skip and do it later in
                                                <strong>Settings → Branches</strong>.
                                            </p>
                                        </div>

                                        <div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-3">
                                            <i class="ti ti-info-circle mt-1 flex-shrink-0"></i>
                                            <span class="small">The default branch is already active and ready to use. Updating it here is optional.</span>
                                        </div>

                                        <div v-if="stepError" class="alert alert-danger py-2 mb-3">{{ stepError }}</div>

                                        <div class="row g-3 mb-4">
                                            <div class="col-md-8">
                                                <VInput
                                                    id="branch_name"
                                                    v-model="branchForm.name"
                                                    label="Branch Name"
                                                    :required="true"
                                                    :error="branchErrors.name?.[0]"
                                                    placeholder="Head Office"
                                                />
                                            </div>
                                            <div class="col-md-4">
                                                <VInput
                                                    id="branch_code"
                                                    v-model="branchForm.code"
                                                    label="Branch Code"
                                                    :required="true"
                                                    :error="branchErrors.code?.[0]"
                                                    placeholder="HO"
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <VInput
                                                    id="branch_phone"
                                                    v-model="branchForm.phone"
                                                    label="Phone"
                                                    placeholder="Optional"
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <VInput
                                                    id="branch_email"
                                                    v-model="branchForm.email"
                                                    label="Email"
                                                    placeholder="Optional"
                                                />
                                            </div>
                                            <div class="col-12">
                                                <VInput
                                                    id="branch_address"
                                                    v-model="branchForm.address"
                                                    label="Address"
                                                    placeholder="Optional"
                                                />
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between">
                                            <button type="button" class="btn btn-outline-secondary" @click="currentStep--">
                                                <i class="ti ti-arrow-left me-1"></i> Back
                                            </button>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-secondary" :disabled="isLoading" @click="skipBranchStep">
                                                    Skip for now
                                                </button>
                                                <button type="button" class="btn btn-primary px-4" :disabled="isLoading" @click="submitBranchStep">
                                                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                                                    Save & Continue <i class="ti ti-arrow-right ms-1"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Step 3: All Set -->
                                    <template v-else-if="currentStep === 2">
                                        <div class="text-center py-4">
                                            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle mb-3" style="width: 80px; height: 80px;">
                                                <i class="ti ti-circle-check text-success" style="font-size: 42px;"></i>
                                            </div>
                                            <h3 class="fw-bold mb-2">You're all set!</h3>
                                            <p class="text-muted mb-0">Your workspace has been configured. Click below to enter your dashboard.</p>
                                        </div>

                                        <div v-if="stepError" class="alert alert-danger py-2 mt-3">{{ stepError }}</div>

                                        <div class="d-flex justify-content-center mt-4">
                                            <button type="button" class="btn btn-primary px-5" :disabled="isLoading" @click="finishOnboarding">
                                                <span v-if="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                                                Go to Dashboard <i class="ti ti-arrow-right ms-1"></i>
                                            </button>
                                        </div>
                                    </template>

                                </div>
                            </div>

                            <div class="my-4 text-center copyright-text">
                                <p class="text-muted small">Copyright &copy; 2025 Sky ERP Pro</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminAuthStore } from '@/stores/admin/auth';
import { useAdminLocationStore } from '@/stores/admin/location.js';
import { apiAdmin } from '@/helpers/api';
import appLogo from '@/assets/images/logo.svg';
import appLogoWhite from '@/assets/images/logo-white.svg';
import { storeToRefs } from 'pinia';

const router = useRouter();
const authStore = useAdminAuthStore();
const locationStore = useAdminLocationStore();
const { provinces } = storeToRefs(locationStore);

const steps = ['Company Details', 'Branch Setup', 'All Set!'];
const currentStep = ref(0);
const isLoading = ref(false);
const isLoadingData = ref(true);
const stepError = ref('');
const fieldErrors = ref({});
const branchErrors = ref({});

const districtOptions = ref([]);
const palikaOptions = ref([]);
const wardOptions = ref([]);
const suppressCascade = ref(false);

const companyForm = reactive({
    company_name: '',
    legal_name: '',
    phone: '',
    pan: '',
    address: '',
    website: '',
    province_id: '',
    district_id: '',
    palika_id: '',
    ward_id: '',
    postal_code: '',
});

const branchForm = reactive({
    branchId: null,
    name: '',
    code: '',
    phone: '',
    email: '',
    address: '',
    is_head_office: true,
    is_active: true,
});

onMounted(async () => {
    document.body.classList.add('account-page', 'bg-white');
    await loadInitialData();
});

onBeforeUnmount(() => {
    document.body.classList.remove('account-page', 'bg-white');
});

const loadInitialData = async () => {
    isLoadingData.value = true;
    try {
        const [settingRes] = await Promise.all([
            apiAdmin('setting', 'get'),
            locationStore.loadProvinces(),
        ]);

        const d = settingRes.data?.data ?? {};
        companyForm.company_name = d.company_name ?? '';
        companyForm.legal_name = d.legal_name ?? '';
        companyForm.phone = d.phone ?? '';
        companyForm.pan = d.pan ?? '';
        companyForm.address = d.address ?? '';
        companyForm.website = d.website ?? '';
        companyForm.postal_code = d.postal_code ?? '';

        suppressCascade.value = true;
        companyForm.province_id = d.province_id ? String(d.province_id) : '';
        companyForm.district_id = d.district_id ? String(d.district_id) : '';
        companyForm.palika_id = d.palika_id ? String(d.palika_id) : '';
        companyForm.ward_id = d.ward_id ? String(d.ward_id) : '';

        if (d.province_id) {
            await locationStore.loadDistricts(d.province_id);
            districtOptions.value = [...locationStore.districts];
        }
        if (d.district_id) {
            await locationStore.loadPalikas(d.district_id);
            palikaOptions.value = [...locationStore.palikas];
        }
        if (d.palika_id) {
            await locationStore.loadWards(d.palika_id);
            wardOptions.value = [...locationStore.wards];
        }

        suppressCascade.value = false;

        // Pre-fill branch from the auto-created default branch
        const branchRes = await apiAdmin('branch', 'get');
        const branches = branchRes.data?.data ?? [];
        if (branches.length > 0) {
            const headOffice = branches.find((b) => b.is_head_office) ?? branches[0];
            branchForm.name = headOffice.name ?? '';
            branchForm.code = headOffice.code ?? '';
            branchForm.phone = headOffice.phone ?? '';
            branchForm.email = headOffice.email ?? '';
            branchForm.address = headOffice.address ?? '';
            branchForm.branchId = headOffice.id;
        }
    } finally {
        isLoadingData.value = false;
    }
};

const stepCircleClass = (i) => {
    if (currentStep.value > i) {
        return 'bg-success border-success text-white';
    }
    if (currentStep.value === i) {
        return 'bg-primary border-primary text-white';
    }
    return 'bg-white border-secondary text-muted';
};

watch(() => companyForm.province_id, async (pid) => {
    if (suppressCascade.value) { return; }
    companyForm.district_id = '';
    companyForm.palika_id = '';
    companyForm.ward_id = '';
    palikaOptions.value = [];
    wardOptions.value = [];
    if (!pid) { districtOptions.value = []; return; }
    await locationStore.loadDistricts(pid);
    districtOptions.value = [...locationStore.districts];
});

watch(() => companyForm.district_id, async (did) => {
    if (suppressCascade.value) { return; }
    companyForm.palika_id = '';
    companyForm.ward_id = '';
    wardOptions.value = [];
    if (!did) { palikaOptions.value = []; return; }
    await locationStore.loadPalikas(did);
    palikaOptions.value = [...locationStore.palikas];
});

watch(() => companyForm.palika_id, async (palikaId) => {
    if (suppressCascade.value) { return; }
    companyForm.ward_id = '';
    if (!palikaId) { wardOptions.value = []; return; }
    await locationStore.loadWards(palikaId);
    wardOptions.value = [...locationStore.wards];
});

const onWardSelect = (wardId) => {
    if (!wardId) { return; }
    const w = wardOptions.value.find((x) => String(x.id) === String(wardId));
    if (w?.postal_code) {
        companyForm.postal_code = w.postal_code;
    }
};

const submitCompanyStep = async () => {
    stepError.value = '';
    fieldErrors.value = {};

    const errors = {};
    if (!companyForm.phone?.trim()) { errors.phone = ['Phone is required.']; }
    if (!companyForm.pan?.trim()) { errors.pan = ['PAN / Reg. No. is required.']; }
    if (!companyForm.province_id) { errors.province_id = ['Province is required.']; }
    if (!companyForm.district_id) { errors.district_id = ['District is required.']; }
    if (!companyForm.palika_id) { errors.palika_id = ['Palika is required.']; }
    if (!companyForm.ward_id) { errors.ward_id = ['Ward is required.']; }
    if (!companyForm.address?.trim()) { errors.address = ['Street / Building detail is required.']; }

    if (Object.keys(errors).length) {
        fieldErrors.value = errors;
        stepError.value = 'Please fill in all required fields.';
        return;
    }

    isLoading.value = true;

    try {
        await apiAdmin('onboarding/company', 'put', {
            legal_name: companyForm.legal_name || undefined,
            phone: companyForm.phone,
            pan: companyForm.pan,
            address: companyForm.address,
            ward_id: companyForm.ward_id || undefined,
            postal_code: companyForm.postal_code || undefined,
            website: companyForm.website || undefined,
        });
        currentStep.value = 1;
    } catch (err) {
        const res = err?.response;
        if (res?.status === 422 && res.data?.errors) {
            fieldErrors.value = res.data.errors;
            stepError.value = res.data.message || 'Please fix the errors below.';
        } else {
            stepError.value = res?.data?.message || 'Failed to save company details.';
        }
    } finally {
        isLoading.value = false;
    }
};

const submitBranchStep = async () => {
    stepError.value = '';
    branchErrors.value = {};
    isLoading.value = true;

    try {
        if (branchForm.branchId) {
            // Update the auto-created branch
            await apiAdmin(`branch/${branchForm.branchId}`, 'put', {
                name: branchForm.name,
                code: branchForm.code,
                phone: branchForm.phone || undefined,
                email: branchForm.email || undefined,
                address: branchForm.address || undefined,
                is_head_office: branchForm.is_head_office,
                is_active: true,
            });
        } else {
            await apiAdmin('branch', 'post', {
                name: branchForm.name,
                code: branchForm.code,
                phone: branchForm.phone || undefined,
                email: branchForm.email || undefined,
                address: branchForm.address || undefined,
                is_head_office: branchForm.is_head_office,
                is_active: true,
            });
        }
        currentStep.value = 2;
    } catch (err) {
        const res = err?.response;
        if (res?.status === 422 && res.data?.errors) {
            branchErrors.value = res.data.errors;
            stepError.value = res.data.message || 'Please fix the errors below.';
        } else {
            stepError.value = res?.data?.message || 'Failed to save branch.';
        }
    } finally {
        isLoading.value = false;
    }
};

const skipBranchStep = () => {
    stepError.value = '';
    branchErrors.value = {};
    currentStep.value = 2;
};

const finishOnboarding = async () => {
    stepError.value = '';
    isLoading.value = true;

    try {
        await authStore.completeOnboarding();
        router.push({ name: 'admin.branch-select' });
    } catch (err) {
        stepError.value = err?.response?.data?.message || 'Something went wrong. Please try again.';
    } finally {
        isLoading.value = false;
    }
};
</script>
