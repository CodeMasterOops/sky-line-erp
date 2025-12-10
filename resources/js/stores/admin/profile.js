import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";
import {useAdminAuthStore} from "@/stores/admin/auth";

export const useProfileStore = defineStore('admin-profile', {
    state: () => {
        const authUser = localStorage.getItem('admin_user');
        return {
            profile: {
                data: authUser ? JSON.parse(authUser) : {},
                loading: false
            },
        }
    },
    actions: {
        getProfile() {
            if (!Object.keys(this.profile.data).length) {
                this.profile.loading = true;
                return apiAdmin(`profile`)
                    .then((res) => {
                        this.profile.data = res.data.data;
                        this.setAuthUser(res.data.data);
                        useAdminAuthStore().setPermissions(res.data.data.user_type, res.data.permissions)
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.profile.loading = false;
                    });
            }
        },
        updateProfile(form) {
            return apiAdmin(`profile/update`, 'post', form)
                .then((res) => {
                    this.profile.data = res.data.data;
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        },
        changePassword(form) {
            return apiAdmin(`profile/change-password`, 'put', form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        setAuthUser(user) {
            this.profile.data = user;
            localStorage.setItem('admin_user', JSON.stringify(user));
        },
    }
})
