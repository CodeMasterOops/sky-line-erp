import {defineStore} from 'pinia'
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";
import {useSuperAdminAuthStore} from "@/stores/super-admin/auth";

export const useSuperAdminProfileStore = defineStore('super-admin-profile', {
    state: () => {
        return {
            profile: {
                data: {},
                loading: false
            },
        }
    },
    actions: {
        getProfile(refetch = false) {
            if (!Object.keys(this.profile.data).length || refetch) {
                this.profile.loading = true;
                return apiSuperAdmin(`profile`)
                    .then((res) => {
                        this.profile.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.profile.loading = false;
                    });
            }
        },
        updateProfile(form) {
            return apiSuperAdmin(`profile/update`, 'post', form)
                .then((res) => {
                    this.profile.data = res.data.data;
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        },
        changePassword(form) {
            return apiSuperAdmin(`profile/change-password`, 'put', form)
                .then((res) => {
                    useSuperAdminAuthStore().removeAuthToken();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
    }
})
