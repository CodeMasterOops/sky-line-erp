import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'payment-mode';

export const usePaymentModeStore = defineStore('payment-mode', {
    state: () => ({
        paymentModes: {
            data: [],
            loading: false
        },
        paymentMode: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getPaymentModes(refetch = false) {
            if (!this.paymentModes.data.length || refetch) {
                this.paymentModes.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.paymentModes.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.paymentModes.loading = false;
                    })
            }
        },
        storePaymentMode(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.paymentModes.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getPaymentMode(id) {
            this.paymentMode.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.paymentMode.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.paymentMode.loading = false;
                })
        },
        updatePaymentMode(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.paymentModes.data[this.paymentModes.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deletePaymentMode(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.paymentModes.data = this.paymentModes.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
