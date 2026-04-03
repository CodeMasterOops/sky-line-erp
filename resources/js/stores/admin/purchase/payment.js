import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'payment';

export const usePaymentStore = defineStore('payment', {
    state: () => ({
        payments: {
            data: [],
            meta: {},
            loading: false
        },
        payment: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getPayments({filter}) {
            this.payments.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.payments.data = res.data.data;
                    this.payments.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.payments.loading = false;
                });
        },
        storePayment(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.payments.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getPayment(id) {
            this.payment.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.payment.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.payment.loading = false;
                });
        },
        updatePayment(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.payments.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.payments.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approvePayment(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.payments.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.payments.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deletePayment(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.payments.data = this.payments.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
