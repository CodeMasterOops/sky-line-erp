import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'receipt';

export const useReceiptStore = defineStore('receipt', {
    state: () => ({
        receipts: {
            data: [],
            meta: {},
            loading: false
        },
        receipt: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getReceipts({filter}) {
            this.receipts.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.receipts.data = res.data.data;
                    this.receipts.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.receipts.loading = false;
                });
        },
        storeReceipt(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.receipts.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getReceipt(id) {
            this.receipt.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.receipt.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.receipt.loading = false;
                });
        },
        updateReceipt(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.receipts.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.receipts.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveReceipt(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.receipts.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.receipts.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteReceipt(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.receipts.data = this.receipts.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
