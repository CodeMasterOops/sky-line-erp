<template>
    <main class="main-wrapper">
        <div class="account-content">
            <div class="login-wrapper login-new">
                <div class="row w-100">
                    <div class="col-lg-5 mx-auto">
                        <div class="login-content user-login">
                            <div class="login-logo">
                                <img :src="appLogo" alt="img" />
                                <router-link :to="{ name: 'admin.dashboard' }" class="login-logo logo-white">
                                    <img :src="appLogoWhite" alt="Img" />
                                </router-link>
                            </div>
                            <div v-if="!authStore.appSettings.registrationEnabled" class="card">
                                <div class="card-body p-5 text-center">
                                    <div class="login-userheading mb-4">
                                        <h3>Registration Disabled</h3>
                                        <h4>New registrations are not available at this time.</h4>
                                    </div>
                                    <p class="text-muted mb-4">
                                        To request an account, please contact our support team.
                                    </p>
                                    <a
                                        v-if="authStore.appSettings.supportEmail"
                                        :href="`mailto:${authStore.appSettings.supportEmail}`"
                                        class="btn btn-login mb-4"
                                    >
                                        Contact Support
                                    </a>
                                    <div class="signinform">
                                        <h4>
                                            Already have an account?
                                            <router-link :to="{ name: 'admin.login' }" class="hover-a">Sign In</router-link>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <form v-else @submit.prevent="submitForm">
                                <div class="card">
                                    <div class="card-body p-5">
                                        <div class="login-userheading">
                                            <h3>Register</h3>
                                            <h4>Create New Sky ERP Pro Account</h4>
                                        </div>
                                        <div v-if="errorMessage" class="mb-3">
                                            <div class="alert alert-danger" role="alert">
                                                {{ errorMessage }}
                                            </div>
                                        </div>
                                        <div v-if="successMessage" class="mb-3">
                                            <div class="alert alert-success" role="alert">
                                                {{ successMessage }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Company Name <span class="text-danger"> *</span></label>
                                            <div class="input-group">
                                                <input
                                                    type="text"
                                                    v-model="form.company_name"
                                                    class="form-control border-end-0"
                                                    :class="{ 'is-invalid': fieldErrors.company_name }"
                                                    placeholder="Your company name"
                                                />
                                                <span class="input-group-text border-start-0">
                                                    <i class="ti ti-building"></i>
                                                </span>
                                            </div>
                                            <div v-if="fieldErrors.company_name" class="invalid-feedback d-block">
                                                {{ fieldErrors.company_name[0] }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Your Name <span class="text-danger"> *</span></label>
                                            <div class="input-group">
                                                <input
                                                    type="text"
                                                    v-model="form.name"
                                                    class="form-control border-end-0"
                                                    :class="{ 'is-invalid': fieldErrors.name }"
                                                    placeholder="Full name"
                                                />
                                                <span class="input-group-text border-start-0">
                                                    <i class="ti ti-user"></i>
                                                </span>
                                            </div>
                                            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                                                {{ fieldErrors.name[0] }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email <span class="text-danger"> *</span></label>
                                            <div class="input-group">
                                                <input
                                                    type="email"
                                                    v-model="form.email"
                                                    class="form-control border-end-0"
                                                    :class="{ 'is-invalid': fieldErrors.email }"
                                                    placeholder="you@company.com"
                                                />
                                                <span class="input-group-text border-start-0">
                                                    <i class="ti ti-mail"></i>
                                                </span>
                                            </div>
                                            <div v-if="fieldErrors.email" class="invalid-feedback d-block">
                                                {{ fieldErrors.email[0] }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password <span class="text-danger"> *</span></label>
                                            <div class="pass-group">
                                                <input
                                                    :type="showPassword ? 'text' : 'password'"
                                                    v-model="form.password"
                                                    class="pass-input form-control"
                                                    :class="{ 'is-invalid': fieldErrors.password }"
                                                    placeholder="Min. 8 characters"
                                                />
                                                <span
                                                    class="ti toggle-password"
                                                    :class="showPassword ? 'ti-eye text-gray-9' : 'ti-eye-off text-gray-9'"
                                                    @click="togglePassword"
                                                ></span>
                                            </div>
                                            <div v-if="fieldErrors.password" class="invalid-feedback d-block">
                                                {{ fieldErrors.password[0] }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Confirm Password <span class="text-danger"> *</span></label>
                                            <div class="pass-group">
                                                <input
                                                    :type="showConfirmPassword ? 'text' : 'password'"
                                                    v-model="form.password_confirmation"
                                                    class="pass-inputs form-control"
                                                    placeholder="Repeat password"
                                                />
                                                <span
                                                    class="ti toggle-passwords"
                                                    :class="showConfirmPassword ? 'ti-eye text-gray-9' : 'ti-eye-off text-gray-9'"
                                                    @click="toggleConfirmPassword"
                                                ></span>
                                            </div>
                                        </div>
                                        <div class="form-login authentication-check">
                                            <div class="row">
                                                <div class="col-sm-8">
                                                    <div class="custom-control custom-checkbox justify-content-start">
                                                        <div class="custom-control custom-checkbox">
                                                            <label class="checkboxs ps-4 mb-0 pb-0 line-height-1">
                                                                <input type="checkbox" v-model="form.agreeToTerms" />
                                                                <span class="checkmarks"></span>I agree to the
                                                                <a href="#" class="text-primary">Terms & Privacy</a>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-login">
                                            <button type="submit" class="btn btn-login" :disabled="isLoading">
                                                <span v-if="isLoading">Creating Account...</span>
                                                <span v-else>Sign Up</span>
                                            </button>
                                        </div>
                                        <div class="signinform">
                                            <h4>
                                                Already have an account ?
                                                <router-link :to="{ name: 'admin.login' }" class="hover-a">Sign In
                                                    Instead</router-link>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                            <p>Copyright &copy; 2025 Sky ERP Pro</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, watch } from "vue";
import { useRouter } from "vue-router";
import { useAdminAuthStore } from "@/stores/admin/auth";
import appLogo from "@/assets/images/logo.svg";
import appLogoWhite from "@/assets/images/logo-white.svg";

const router = useRouter();
const authStore = useAdminAuthStore();

authStore.fetchAppSettings();

const isAccountPage = ref(true);
const form = reactive({
    company_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    agreeToTerms: false,
});

const errorMessage = ref('');
const successMessage = ref('');
const fieldErrors = ref({});
const isLoading = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const updateBodyClass = (isAccountPage) => {
    if (isAccountPage) {
        document.body.classList.add("account-page", "bg-white");
    } else {
        document.body.classList.remove("account-page", "bg-white");
    }
};

onMounted(() => {
    updateBodyClass(isAccountPage.value);
});

onBeforeUnmount(() => {
    updateBodyClass(false);
});

watch(isAccountPage, (newValue) => {
    updateBodyClass(newValue);
});

const togglePassword = () => {
    showPassword.value = !showPassword.value;
};

const toggleConfirmPassword = () => {
    showConfirmPassword.value = !showConfirmPassword.value;
};

const submitForm = async () => {
    errorMessage.value = '';
    successMessage.value = '';
    fieldErrors.value = {};

    if (!form.agreeToTerms) {
        errorMessage.value = 'You must agree to the Terms & Privacy.';
        return;
    }

    isLoading.value = true;

    try {
        await authStore.register({
            company_name: form.company_name,
            name: form.name,
            email: form.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });

        successMessage.value = 'Account created! Setting up your workspace...';

        router.push({ name: 'admin.workspace-setup' });
    } catch (err) {
        const response = err?.response;
        if (response?.status === 422 && response.data?.errors) {
            fieldErrors.value = response.data.errors;
            errorMessage.value = response.data.message || 'Please fix the errors below.';
        } else {
            errorMessage.value = response?.data?.message || 'Something went wrong. Please try again.';
        }
    } finally {
        isLoading.value = false;
    }
};
</script>
