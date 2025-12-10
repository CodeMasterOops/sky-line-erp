import {defineStore} from 'pinia'
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

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
        getProfile() {
            if (!Object.keys(this.profile.data).length) {
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
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
    }
})
