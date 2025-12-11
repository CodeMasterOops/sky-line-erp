import {defineStore} from 'pinia'
import {apiSuperAdmin, apiFront} from "@/helpers/api";

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
