import {defineStore} from 'pinia'
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'support';

export const useSupportStore = defineStore('super-admin-support', {
    state: () => ({
        support: {
            data: {
                support_phones: [],
                support_emails: [],
                support_whatsapp: [],
                support_social_links: [],
                support_videos: [],
            },
            loading: false,
        },
    }),
    actions: {
        getSupportSettings(refetch = false) {
            if (!this.support.data.support_phones.length && !refetch || refetch) {
                this.support.loading = true;
                return apiSuperAdmin(apiUrl)
                    .then((res) => {
                        this.support.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    })
                    .finally(() => {
                        this.support.loading = false;
                    });
            }
        },
        updateSupportSettings(form) {
            return apiSuperAdmin(apiUrl, 'post', form)
                .then((res) => {
                    this.support.data = res.data.data;
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
    },
});
