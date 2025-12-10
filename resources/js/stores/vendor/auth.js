import {defineStore} from 'pinia'
import {apiVendor, apiFront} from "@/helpers/api";

export const useVendorAuthStore = defineStore('vendor-auth', {
    state: () => {
        return {
            authUser: {
                access_token: localStorage.getItem("v_access_token")
            }
        }
    },
    actions: {
        login(form) {
            return apiFront('vendor/login', 'post', form)
                .then((res) => {
                    this.setAuthToken(res.data.access_token);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        logout() {
            return apiVendor('logout', 'post')
                .then((res) => {
                    this.removeAuthToken();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        setAuthToken(token) {
            this.authUser.access_token = token;
            localStorage.setItem('v_access_token', token);
        },
        removeAuthToken() {
            this.authUser.access_token = '';
            this.authUser.user_type = '';
            localStorage.removeItem('v_access_token');
        },
    }
})
