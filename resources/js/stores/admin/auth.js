import {defineStore} from 'pinia'
import {apiAdmin, apiFront} from "@/helpers/api";
import {storedPermissions} from "@/helpers/helper";

export const useAdminAuthStore = defineStore('admin-auth', {
    state: () => {
        return {
            authUser: {
                access_token: localStorage.getItem("access_token"),
                user_type: localStorage.getItem('user_type'),
                permissions: storedPermissions()
            }
        }
    },
    actions: {
        login(form) {
            return apiFront('admin/login', 'post', form)
                .then((res) => {
                    this.setAuthToken(res.data.access_token);
                    this.setPermissions(res.data.user?.user_type, res.data.permissions);
                    return res;
                }).catch((err) => {
                    throw err;
                })
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
        setAuthToken(token) {
            this.authUser.access_token = token;
            localStorage.setItem('access_token', token);
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
        removeAuthToken() {
            this.authUser.access_token = '';
            this.authUser.user_type = '';
            this.authUser.permissions = [];
            localStorage.removeItem('user_type');
            localStorage.removeItem('permissions');
            localStorage.removeItem('access_token');
            localStorage.removeItem('admin_user');
        },
    }
})
