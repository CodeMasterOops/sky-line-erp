import {defineStore} from 'pinia'
import {apiAdmin, apiFront} from "@/helpers/api";
import {storedPermissions} from "@/helpers/helper";
import Cookies from "js-cookie";

export const useAdminAuthStore = defineStore('admin-auth', {
    state: () => {
        return {
            authUser: {
                access_token: Cookies.get("access_token"),
                user_type: localStorage.getItem('user_type'),
                permissions: storedPermissions(),
                needsOnboarding: localStorage.getItem('needs_onboarding') === 'true',
            }
        }
    },
    actions: {
        login(form) {
            return apiFront('admin/login', 'post', form)
                .then((res) => {
                    this.setAuthToken(res.data.access_token, res.data.expires_at);
                    this.setPermissions(res.data.user?.user_type, res.data.permissions);
                    this.setNeedsOnboarding(res.data.needs_onboarding ?? false);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        register(form) {
            return apiFront('admin/register', 'post', form)
                .then((res) => {
                    this.setAuthToken(res.data.access_token, res.data.expires_at);
                    this.setPermissions(res.data.user?.user_type, res.data.permissions);
                    this.setNeedsOnboarding(res.data.needs_onboarding ?? true);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        logout() {
            return apiAdmin('logout', 'post')
                .then((res) => {
                    this.removeAuthToken();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        completeOnboarding() {
            return apiAdmin('onboarding/complete', 'post')
                .then((res) => {
                    this.setNeedsOnboarding(false);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        setAuthToken(token, expires_at) {
            this.authUser.access_token = token;
            const expiresAt = new Date(expires_at);
            Cookies.set("access_token", token, {
                expires: expiresAt,
                secure: window.location.protocol === 'https:',
                sameSite: "Strict",
                path: '/',
            });
        },
        setPermissions(user_type, permissions = []) {
            if (user_type === 'admin') {
                localStorage.setItem('user_type', 'admin');
                localStorage.removeItem('permissions');
            } else {
                localStorage.removeItem('user_type');
                localStorage.setItem('permissions', permissions.toString());
                this.authUser.permissions = storedPermissions();
            }
            this.authUser.user_type = user_type;
        },
        setNeedsOnboarding(value) {
            this.authUser.needsOnboarding = value;
            localStorage.setItem('needs_onboarding', value ? 'true' : 'false');
        },
        removeAuthToken() {
            this.authUser.access_token = '';
            this.authUser.user_type = '';
            this.authUser.permissions = [];
            this.authUser.needsOnboarding = false;
            localStorage.removeItem('user_type');
            localStorage.removeItem('permissions');
            localStorage.removeItem('needs_onboarding');
            Cookies.remove('access_token', {
                secure: window.location.protocol === 'https:',
                sameSite: "Strict",
                path: '/',
            });
            localStorage.removeItem('admin_user');
        },
    }
})
