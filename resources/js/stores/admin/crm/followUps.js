import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'crm/follow-up';

export const useCrmFollowUpStore = defineStore('crmFollowUp', {
    state: () => ({
        followUps: {
            data: [],
            meta: {},
            loading: false,
        },
    }),

    actions: {
        getFollowUps({ filter, due = false } = {}) {
            this.followUps.loading = true;
            const url = due ? `${apiUrl}/due` : apiUrl;
            const q = new URLSearchParams({ ...filter }).toString();
            return apiAdmin(`${url}?${q}`)
                .then((res) => {
                    this.followUps.data = res.data.data;
                    this.followUps.meta = res.data.meta ?? {};
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.followUps.loading = false;
                });
        },
        storeFollowUp(form) {
            return apiAdmin(apiUrl, 'post', form);
        },
        updateFollowUp(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form);
        },
        completeFollowUp(id, payload = {}) {
            return apiAdmin(`${apiUrl}/${id}/complete`, 'post', payload);
        },
        deleteFollowUp(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete');
        },
    },
});
