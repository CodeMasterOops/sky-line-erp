import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'payment-voucher';

export const usePaymentVoucherStore = defineStore('paymentVoucher', {
    state: () => ({
        vouchers: {
            data: [],
            meta: {},
            loading: false
        },
        voucher: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getVouchers({filter}) {
            this.vouchers.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.vouchers.data = res.data.data;
                    this.vouchers.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.vouchers.loading = false;
                });
        },
        storeVoucher(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.vouchers.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getVoucher(id) {
            this.voucher.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.voucher.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.voucher.loading = false;
                });
        },
        updateVoucher(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.vouchers.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.vouchers.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveVoucher(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.vouchers.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.vouchers.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteVoucher(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.vouchers.data = this.vouchers.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
