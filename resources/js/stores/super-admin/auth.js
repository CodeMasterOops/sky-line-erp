import {defineStore} from 'pinia'
import {apiSuperAdmin, apiFront} from "@/helpers/api";
import {useAdminAuthStore} from "@/stores/admin/auth";

export const useSuperAdminAuthStore = defineStore('super-admin-auth', {
    state: () => {
        return {
            authUser: {
                access_token: localStorage.getItem("s_access_token"),
            }
        }
    },
    actions: {
        login(form) {
            return apiFront('super-admin/login', 'post', form)
                .then((res) => {
                    this.setAuthToken(res.data.access_token);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        logout() {
            return apiSuperAdmin('logout', 'post')
                .then((res) => {
                    this.removeAuthToken();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        companyLogin(id) {
            return apiSuperAdmin(`company/${id}/login`, 'post')
                .then((res) => {
                    const adminAuthStore = useAdminAuthStore();
                    adminAuthStore.setAuthToken(res.data.access_token, res.data.expires_at);
                    adminAuthStore.setPermissions(res.data.user?.user_type, res.data.permissions);
                    adminAuthStore.setNeedsOnboarding(res.data.needs_onboarding ?? false);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        setAuthToken(token) {
            this.authUser.access_token = token;
            localStorage.setItem('s_access_token', token);
        },
        removeAuthToken() {
            this.authUser.access_token = '';
            localStorage.removeItem('s_access_token');
        },
    }
})
