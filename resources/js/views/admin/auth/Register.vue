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
                            <form @submit.prevent="submitForm">
                                <div class="card">
                                    <div class="card-body p-5">
                                        <div class="login-userheading">
                                            <h3>Register</h3>
                                            <h4>Create New Dreamspos Account</h4>
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
                                            <label class="form-label">Name <span class="text-danger"> *</span></label>
                                            <div class="input-group">
                                                <input type="text" v-model="form.name" class="form-control border-end-0" />
                                                <span class="input-group-text border-start-0">
                                                    <i class="ti ti-user"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email <span class="text-danger"> *</span></label>
                                            <div class="input-group">
                                                <input type="text" v-model="form.email" class="form-control border-end-0" />
                                                <span class="input-group-text border-start-0">
                                                    <i class="ti ti-mail"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password <span class="text-danger"> *</span></label>
                                            <div class="pass-group">
                                                <input :type="showPassword ? 'text' : 'password'" v-model="form.password"
                                                    class="pass-input form-control" />
                                                <span class="ti toggle-password"
                                                    :class="showPassword ? 'ti-eye text-gray-9' : 'ti-eye-off text-gray-9'"
                                                    @click="togglePassword"></span>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Confirm Password <span class="text-danger">
                                                    *</span></label>
                                            <div class="pass-group">
                                                <input :type="showConfirmPassword ? 'text' : 'password'"
                                                    v-model="form.confirmPassword" class="pass-inputs form-control" />
                                                <span class="ti toggle-passwords"
                                                    :class="showConfirmPassword ? 'ti-eye text-gray-9' : 'ti-eye-off text-gray-9'"
                                                    @click="toggleConfirmPassword"></span>
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
                                        <div class="form-setlogin or-text">
                                            <h4>OR</h4>
                                        </div>
                                        <div class="mt-2">
                                            <div class="d-flex align-items-center justify-content-center flex-wrap">
                                                <div class="text-center me-2 flex-fill">
                                                    <a href="javascript:void(0);"
                                                        class="br-10 p-2 btn btn-info d-flex align-items-center justify-content-center">
                                                        <img class="img-fluid m-1" :src="facebookLogo" alt="Facebook" />
                                                    </a>
                                                </div>
                                                <div class="text-center me-2 flex-fill">
                                                    <a href="javascript:void(0);"
                                                        class="btn btn-white br-10 p-2 border d-flex align-items-center justify-content-center">
                                                        <img class="img-fluid m-1" :src="googleLogo" alt="Google" />
                                                    </a>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="my-4 d-flex justify-content-center align-items-center copyright-text">
                            <p>Copyright &copy; 2025 DreamsPOS</p>
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
import appLogo from "@/assets/images/logo.svg";
import appLogoWhite from "@/assets/images/logo-white.svg";
import facebookLogo from "@/assets/images/icons/facebook-logo.svg";
import googleLogo from "@/assets/images/icons/google-logo.svg";
 

const router = useRouter();

const isAccountPage = ref(true);
const form = reactive({
    name: '',
    email: '',
    password: '',
    confirmPassword: '',
    agreeToTerms: false
});

const errorMessage = ref('');
const successMessage = ref('');
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

const submitForm = () => {
    errorMessage.value = '';
    successMessage.value = '';
    isLoading.value = true;

    if (!form.name.trim()) {
        errorMessage.value = 'Name is required.';
        isLoading.value = false;
        return;
    }

    if (!form.email.trim()) {
        errorMessage.value = 'Email is required.';
        isLoading.value = false;
        return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(form.email)) {
        errorMessage.value = 'Please enter a valid email address.';
        isLoading.value = false;
        return;
    }

    if (!form.password) {
        errorMessage.value = 'Password is required.';
        isLoading.value = false;
        return;
    }

    if (form.password.length < 6) {
        errorMessage.value = 'Password must be at least 6 characters long.';
        isLoading.value = false;
        return;
    }

    if (form.password !== form.confirmPassword) {
        errorMessage.value = 'Passwords do not match.';
        isLoading.value = false;
        return;
    }

    if (!form.agreeToTerms) {
        errorMessage.value = 'You must agree to the Terms & Privacy.';
        isLoading.value = false;
        return;
    }

    setTimeout(() => {
        const registeredUsers = JSON.parse(localStorage.getItem('registeredUsers') || '[]');

        const emailExists = registeredUsers.some(user => user.email === form.email.trim());
        if (emailExists) {
            errorMessage.value = 'This email is already registered. Please use a different email.';
            isLoading.value = false;
            return;
        }

        registeredUsers.push({
            email: form.email.trim(),
            password: form.password,
            name: form.name.trim()
        });

        localStorage.setItem('registeredUsers', JSON.stringify(registeredUsers));

        successMessage.value = 'Account created successfully! Redirecting to sign in...';
        isLoading.value = false;

        setTimeout(() => {
            router.push({
                name: 'admin.login',
                query: { email: form.email.trim() }
            });
        }, 2000);
    }, 1000);
};
</script>
