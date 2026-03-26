import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'stock-adjustment';

export const useStockAdjustmentStore = defineStore('stockAdjustment', {
    state: () => ({
        adjustments: {
            data: [],
            meta: {},
            loading: false
        },
        adjustment: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAdjustments({filter}) {
            this.adjustments.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.adjustments.data = res.data.data;
                    this.adjustments.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.adjustments.loading = false;
                });
        },
        storeAdjustment(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.adjustments.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getAdjustment(id) {
            this.adjustment.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.adjustment.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.adjustment.loading = false;
                });
        },
        updateAdjustment(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.adjustments.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.adjustments.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveAdjustment(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.adjustments.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.adjustments.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteAdjustment(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.adjustments.data = this.adjustments.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
