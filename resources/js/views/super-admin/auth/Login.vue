<template>
    <main class="main-wrapper">
        <div class="account-content">
            <div class="login-wrapper login-new">
                <div class="row w-100">
                    <div class="col-lg-5 mx-auto">
                        <div class="login-content user-login">
                            <div class="login-logo">
                                <img :src="appLogo" alt="img">
                                <router-link to="/super-admin/dashboard" class="login-logo logo-white">
                                    <img :src="appLogoWhite" alt="Img">
                                </router-link>
                            </div>
                            <form @submit.prevent="login">
                                <div class="card">
                                    <div class="card-body p-5">
                                        <div class="login-userheading">
                                            <h3>Sign In</h3>
                                            <h4>Access the Super Admin panel using your email and passcode.</h4>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Email <span class="text-danger"> *</span></label>
                                            <div class="input-group">
                                                <input type="email" v-model="form.email" @blur="validateField('email')"
                                                    class="form-control border-end-0">
                                                <span class="input-group-text border-start-0">
                                                    <i class="fa fa-envelope-o"></i>
                                                </span>
                                            </div>
                                            <p v-if="errors.email" class="text-danger">
                                                {{ errors.email }}
                                            </p>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Password <span class="text-danger">
                                                    *</span></label>
                                            <div class="pass-group">
                                                <input :type="showPassword ? 'text' : 'password'"
                                                    v-model="form.password" @blur="validateField('password')"
                                                    class="pass-input form-control">
                                                <span class="ti toggle-password"
                                                    :class="showPassword ? 'ti-eye text-gray-9' : 'ti-eye-off text-gray-9'"
                                                    @click="togglePassword"></span>
                                            </div>
                                            <p v-if="errors.password" class="text-danger">
                                                {{ errors.password }}
                                            </p>
                                        </div>

                                        <div class="form-login authentication-check">
                                            <div class="row">
                                                <div class="col-12 d-flex align-items-center justify-content-between">
                                                    <div class="custom-control custom-checkbox">
                                                        <label
                                                            class="checkboxs ps-4 mb-0 pb-0 line-height-1 fs-16 text-gray-6">
                                                            <input type="checkbox" v-model="form.rememberMe"
                                                                class="form-control">
                                                            <span class="checkmarks"></span>Remember me
                                                        </label>
                                                    </div>
                                                    <div class="text-end">
                                                        <a class="text-orange fs-16 fw-medium"
                                                            href="javascript:void(0);">Forgot
                                                            Password?</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-login">
                                            <button type="submit" class="btn btn-primary w-100"
                                                :disabled="isSubmitting">
                                                <span v-if="isSubmitting">Signing In...</span>
                                                <span v-else>Sign In</span>
                                            </button>
                                        </div>
                                        
                                        <div class="form-setlogin or-text">
                                            <h4>OR</h4>
                                        </div>
                                        <div class="mt-2">
                                            <div class="d-flex align-items-center justify-content-center flex-wrap">
                                                <div class="text-center me-2 flex-fill">
                                                    <a href="javascript:void(0);"
                                                        class="br-10 p-2 btn btn-info d-flex align-items-center justify-content-center">
                                                        <img class="img-fluid m-1" :src="facebookLogo" alt="Facebook">
                                                    </a>
                                                </div>
                                                <div class="text-center flex-fill">
                                                    <a href="javascript:void(0);"
                                                        class="btn btn-white br-10 p-2  border d-flex align-items-center justify-content-center">
                                                        <img class="img-fluid m-1" :src="googleLogo" alt="Facebook">
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
import appLogo from "@/assets/images/logo.svg";
import appLogoWhite from "@/assets/images/logo-white.svg";
import facebookLogo from "@/assets/images/icons/facebook-logo.svg";
import googleLogo from "@/assets/images/icons/google-logo.svg";

import { useRouter } from "vue-router";
import { reactive, ref } from "vue";
import { toast } from "@/helpers/toast";
import { useYup } from "@/helpers/yup";
import { object, string } from "yup";
import showErrors from "@/helpers/showErrors";
import { useSuperAdminAuthStore } from "@/stores/super-admin/auth.js";

const authStore = useSuperAdminAuthStore();
const router = useRouter()

const form = reactive({
    email: '',
    password: '',
    rememberMe: false,
})

const isSubmitting = ref(false)

const showPassword = ref(false)

const togglePassword = () => {
    showPassword.value = !showPassword.value
}

const validations = object({
    email: string().required('Email is required.').email(),
    password: string().required('Password is required.'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

const login = async () => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await authStore.login(form);
            await router.push({ name: 'super-admin.dashboard' });
            toast(res.status, res.data.message);
        } catch (e) {
            showErrors(e)
        } finally {
            isSubmitting.value = false;
        }
    }
}
</script>
