import {defineStore} from 'pinia'
import {getActivePinia} from 'pinia';
import {apiAdmin, apiFront} from "@/helpers/api";
import Cookies from "js-cookie";
import {useBranchStore} from '@/stores/admin/settings/branch.js';

export const useAdminAuthStore = defineStore('admin-auth', {
    state: () => {
        return {
            authUser: {
                access_token: Cookies.get("access_token"),
                user_type: '',
                permissions: [],
                permissionsLoaded: false,
                needsOnboarding: localStorage.getItem('needs_onboarding') === 'true',
                provisioningPending: localStorage.getItem('provisioning_pending') === 'true',
            },
            appSettings: {
                registrationEnabled: true,
                supportEmail: '',
                loaded: false,
            },
        }
    },
    actions: {
        fetchAppSettings() {
            if (this.appSettings.loaded) {
                return Promise.resolve();
            }
            return apiFront('public/settings', 'get')
                .then((res) => {
                    this.appSettings.registrationEnabled = res.data.registration_enabled;
                    this.appSettings.supportEmail = res.data.support_email;
                    this.appSettings.loaded = true;
                })
                .catch(() => {});
        },
        login(form) {
            return apiFront('admin/login', 'post', form)
                .then((res) => {
                    this.setAuthToken(res.data.access_token, res.data.expires_at);
                    this.clearBranchContext();
                    this.setPermissions(res.data.user?.user_type, res.data.permissions);
                    this.setNeedsOnboarding(res.data.needs_onboarding ?? false);
                    this.setProvisioningPending(false);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        register(form) {
            return apiFront('admin/register', 'post', form)
                .then((res) => {
                    this.setAuthToken(res.data.access_token, res.data.expires_at);
                    this.clearBranchContext();
                    this.setPermissions(res.data.user?.user_type, res.data.permissions);
                    this.setNeedsOnboarding(res.data.needs_onboarding ?? true);
                    this.setProvisioningPending(res.data.provisioning_pending ?? true);
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
        setPermissions(user_type, permissions = '') {
            this.authUser.user_type = user_type ?? '';
            if (user_type !== 'admin') {
                try {
                    this.authUser.permissions = permissions ? JSON.parse(atob(permissions)) : [];
                } catch {
                    this.authUser.permissions = [];
                }
            } else {
                this.authUser.permissions = [];
            }
            this.authUser.permissionsLoaded = true;
        },
        setNeedsOnboarding(value) {
            this.authUser.needsOnboarding = value;
            localStorage.setItem('needs_onboarding', value ? 'true' : 'false');
        },
        setProvisioningPending(value) {
            this.authUser.provisioningPending = value;
            localStorage.setItem('provisioning_pending', value ? 'true' : 'false');
        },
        clearBranchContext() {
            const pinia = getActivePinia();
            if (pinia) {
                useBranchStore(pinia).clearSelectedBranch();
            }
        },
        refreshPermissions() {
            return apiAdmin('profile/permissions')
                .then((res) => {
                    this.setPermissions(res.data.user_type, res.data.permissions);
                }).catch(() => {});
        },
        removeAuthToken() {
            this.authUser.access_token = '';
            this.authUser.user_type = '';
            this.authUser.permissions = [];
            this.authUser.permissionsLoaded = false;
            this.authUser.needsOnboarding = false;
            this.authUser.provisioningPending = false;
            localStorage.removeItem('needs_onboarding');
            localStorage.removeItem('provisioning_pending');
            Cookies.remove('access_token', {
                secure: window.location.protocol === 'https:',
                sameSite: "Strict",
                path: '/',
            });
            localStorage.removeItem('admin_user');
        },
    }
})
