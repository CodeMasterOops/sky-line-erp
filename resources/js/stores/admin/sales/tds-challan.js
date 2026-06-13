import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'tds-challan';

export const useTdsChallanStore = defineStore('tdsChallan', {
    state: () => ({
        challans: {
            data: [],
            meta: {},
            loading: false,
        },
        challan: {
            data: {},
            loading: false,
        },
    }),

    actions: {
        getChallans({filter} = {}) {
            this.challans.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter ?? {}).toString()}`)
                .then((res) => {
                    this.challans.data = res.data.data;
                    this.challans.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.challans.loading = false;
                });
        },
        storeChallan(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.challans.data.unshift(res.data.data);
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
        getChallan(id) {
            this.challan.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.challan.data = res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.challan.loading = false;
                });
        },
        markSubmitted(id) {
            return apiAdmin(`${apiUrl}/${id}/submit`, 'post')
                .then((res) => {
                    const idx = this.challans.data.findIndex((c) => c.id === id);
                    if (idx !== -1) {
                        this.challans.data[idx] = res.data.data;
                    }
                    if (this.challan.data?.id === id) {
                        this.challan.data = res.data.data;
                    }
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
    },
});
