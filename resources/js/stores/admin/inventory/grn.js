import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'grn';

export const useGrnStore = defineStore('grn', {
    state: () => ({
        grns: {
            data: [],
            meta: {},
            loading: false,
        },
        grn: {
            data: {},
            loading: false,
        },
    }),

    actions: {
        getGrns({filter}) {
            this.grns.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.grns.data = res.data.data;
                    this.grns.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.grns.loading = false;
                });
        },
        getGrn(id) {
            this.grn.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.grn.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.grn.loading = false;
                });
        },
        storeGrn(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.grns.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        updateGrn(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.grns.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.grns.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveGrn(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.grns.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.grns.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteGrn(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.grns.data = this.grns.data.filter((d) => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
    },
});
