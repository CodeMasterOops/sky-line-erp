import {defineStore} from 'pinia'
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useVendorProfileStore = defineStore('vendor-profile', {
    state: () => {
        return {
            profile: {
                data:{},
                loading: false
            },
        }
    },
    actions: {
        getProfile() {
            if (!Object.keys(this.profile.data).length) {
                this.profile.loading = true;
                return apiVendor(`profile`)
                    .then((res) => {
                        this.profile.data = res.data.data;
                        this.setAuthUser(res.data.data);
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.profile.loading = false;
                    });
            }
        },
        updateProfile(form) {
            return apiVendor(`profile/update`, 'post', form)
                .then((res) => {
                    this.profile.data = res.data.data;
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        },
        changePassword(form) {
            return apiVendor(`profile/change-password`, 'put', form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        setAuthUser(user) {
            this.profile.data = user;
        },
    }
})
