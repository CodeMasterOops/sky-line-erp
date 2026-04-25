import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'bill';

export const useBillStore = defineStore('bill', {
    state: () => ({
        bills: {
            data: [],
            meta: {},
            loading: false
        },
        bill: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getBills({filter}) {
            this.bills.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.bills.data = res.data.data;
                    this.bills.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.bills.loading = false;
                });
        },
        storeBill(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.bills.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getBill(id) {
            this.bill.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.bill.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.bill.loading = false;
                });
        },
        updateBill(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.bills.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.bills.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveBill(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.bills.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.bills.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        voidBill(id) {
            return apiAdmin(`${apiUrl}/${id}/void`, 'post')
                .then((res) => {
                    const index = this.bills.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.bills.data[index] = res.data.data;
                    }
                    if (this.bill.data?.id === id) {
                        this.bill.data = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteBill(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.bills.data = this.bills.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
