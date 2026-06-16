import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

export const useAdminSupportStore = defineStore('admin-support', {
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
            if (!this.support.data.support_phones.length || refetch) {
                this.support.loading = true;
                return apiAdmin('support')
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
    },
});
