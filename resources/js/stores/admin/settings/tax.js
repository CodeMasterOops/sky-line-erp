import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'tax'

export const useTaxStore = defineStore('tax', {
    state: () => ({
        taxes: {
            data: [],
            meta: {},
            loading: false
        },
        tax: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getTaxes({ filter } = {}) {
            const params = {
                ...(filter ?? {}),
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.taxes.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.taxes.data = res.data.data;
                    this.taxes.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.taxes.loading = false;
                });
        },
        storeTax(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.taxes.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getTax(id) {
            this.tax.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.tax.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.tax.loading = false;
                })
        },
        updateTax(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.taxes.data[this.taxes.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteTax(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.taxes.data = this.taxes.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
