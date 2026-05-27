import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'delivery-challan';

export const useDeliveryChallanStore = defineStore('deliveryChallan', {
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
        getChallans({filter}) {
            this.challans.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.challans.data = res.data.data;
                    this.challans.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.challans.loading = false;
                });
        },
        storeChallan(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.challans.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getChallan(id) {
            this.challan.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.challan.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.challan.loading = false;
                });
        },
        updateChallan(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.challans.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.challans.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveChallan(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.challans.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.challans.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteChallan(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.challans.data = this.challans.data.filter((d) => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
    },
});
