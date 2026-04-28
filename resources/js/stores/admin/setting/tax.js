import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'tax'

export const useTaxStore = defineStore('tax', {
    state: () => ({
        taxes: {
            data: [],
            loading: false
        },
        taxesFetchKey: '',
        tax: {
            data: {},
            loading: false
        }
    }),

    actions: {
        /**
         * @param {boolean} refetch
         * @param {Record<string, string>} [query] e.g. { for: 'line_item' } for VAT-only taxes (invoice/bill lines)
         */
        getTaxes(refetch = false, query = {}) {
            const qs = new URLSearchParams(query).toString();
            const path = qs ? `${apiUrl}?${qs}` : apiUrl;
            const fetchKey = qs || '__all__';
            if (!refetch && this.taxesFetchKey === fetchKey && this.taxes.data.length) {
                return Promise.resolve();
            }
            this.taxes.loading = true;
            return apiAdmin(path)
                .then((res) => {
                    this.taxes.data = res.data.data;
                    this.taxesFetchKey = fetchKey;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
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
