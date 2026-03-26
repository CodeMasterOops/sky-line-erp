import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'stock-transfer';

export const useStockTransferStore = defineStore('stockTransfer', {
    state: () => ({
        transfers: {
            data: [],
            meta: {},
            loading: false
        },
        transfer: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getTransfers({filter}) {
            this.transfers.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.transfers.data = res.data.data;
                    this.transfers.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.transfers.loading = false;
                });
        },
        storeTransfer(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.transfers.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getTransfer(id) {
            this.transfer.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.transfer.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.transfer.loading = false;
                });
        },
        updateTransfer(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.transfers.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.transfers.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveTransfer(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.transfers.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.transfers.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteTransfer(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.transfers.data = this.transfers.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
