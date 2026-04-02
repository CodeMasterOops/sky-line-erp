import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'quotation';

export const useQuotationStore = defineStore('quotation', {
    state: () => ({
        quotations: {
            data: [],
            meta: {},
            loading: false
        },
        quotation: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getQuotations({filter}) {
            this.quotations.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.quotations.data = res.data.data;
                    this.quotations.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.quotations.loading = false;
                });
        },
        storeQuotation(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.quotations.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getQuotation(id) {
            this.quotation.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.quotation.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.quotation.loading = false;
                });
        },
        updateQuotation(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.quotations.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.quotations.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveQuotation(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.quotations.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.quotations.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        convertToInvoice(id, payload) {
            return apiAdmin(`${apiUrl}/${id}/convert-to-invoice`, 'post', payload)
                .then((res) => {
                    const index = this.quotations.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.quotations.data[index] = {
                            ...this.quotations.data[index],
                            invoice_count: (this.quotations.data[index].invoice_count || 0) + 1,
                        };
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        convertToSale(id) {
            return apiAdmin(`${apiUrl}/${id}/convert-to-sale`, 'post')
                .then((res) => {
                    const index = this.quotations.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.quotations.data[index] = {
                            ...this.quotations.data[index],
                            sales_order_count: (this.quotations.data[index].sales_order_count || 0) + 1,
                        };
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteQuotation(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.quotations.data = this.quotations.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
