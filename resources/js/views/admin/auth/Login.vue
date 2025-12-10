<template>
    <main class="admin-auth">
        <div class="container-fluid">
            <div class="authentication-card">
                <div class="card shadow rounded-0 overflow-hidden">
                    <div class="row g-0">
                        <div class="col-lg-6 bg-login d-flex align-items-center justify-content-center">
                            <div class="text-center align-self-center p-1 text-light">
                                <div>
                                    <img :src="appLogo" height="70" alt="Logo">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card-body p-4 p-sm-5">
                                <h5 class="card-title fw-bold mb-4">Admin Login</h5>
                                <form @submit.prevent="login" class="form-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="email" class="form-label">Email Address</label>
                                            <div class="ms-auto position-relative">
                                                <div
                                                    class="position-absolute top-50 translate-middle-y search-icon px-3">
                                                    <i class="fa fa-envelope-o"></i></div>
                                                <input type="email"
                                                       v-model="form.email"
                                                       @input="validateField('email')"
                                                       @blur="validateField('email')"
                                                       :class="['form-control rounded-5 ps-5',{'is-invalid':errors.email}]"
                                                       id="email"
                                                       placeholder="Email Address">
                                            </div>
                                            <p v-if="errors.email" class="text-danger">
                                                {{ errors.email }}
                                            </p>
                                        </div>
                                        <div class="col-12">
                                            <label for="password" class="form-label">Enter Password</label>
                                            <div class="ms-auto position-relative">
                                                <div
                                                    class="position-absolute top-50 translate-middle-y search-icon px-3">
                                                    <i class="fa fa-lock"></i>
                                                </div>
                                                <input type="password"
                                                       v-model="form.password"
                                                       @input="validateField('password')"
                                                       @blur="validateField('password')"
                                                       :class="['form-control rounded-5 ps-5',{'is-invalid':errors.password}]"
                                                       id="password"
                                                       placeholder="Enter Password">
                                            </div>
                                            <p v-if="errors.password" class="text-danger">
                                                {{ errors.password }}
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                       id="flexSwitchCheckChecked" checked="">
                                                <label class="form-check-label" for="flexSwitchCheckChecked">Remember
                                                    Me</label>
                                            </div>
                                        </div>
                                        <div class="col-6 text-end">
                                            <a href="#">
                                                Forgot Password ?
                                            </a>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-grid">
                                                <VButton
                                                    btn-class="btn btn-primary rounded-5"
                                                    btn-label="Sign In"
                                                    :loading="isSubmitting"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
<script setup>
import appLogo from "@/assets/images/app-logo.png";

import {useRouter} from "vue-router";
import {reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import {useYup} from "@/helpers/yup";
import {object, string} from "yup";
import showErrors from "@/helpers/showErrors";
import {useAdminAuthStore} from "@/stores/admin/auth.js";

const authStore = useAdminAuthStore();
const router = useRouter()

const form = reactive({
    email: '',
    password: ''
})

const isSubmitting = ref(false)

const validations = object({
    email: string().required('Email is required.').email(),
    password: string().required('Password is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const login = async () => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await authStore.login(form);
            await router.push({name: 'admin.dashboard'});
            toast(res.status, res.data.message);
        } catch (e) {
            showErrors(e)
        } finally {
            isSubmitting.value = false;
        }
    }
}
</script>
