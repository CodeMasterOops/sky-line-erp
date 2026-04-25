import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'invoice';

export const useInvoiceStore = defineStore('invoice', {
    state: () => ({
        invoices: {
            data: [],
            meta: {},
            loading: false
        },
        invoice: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getInvoices({filter}) {
            this.invoices.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.invoices.data = res.data.data;
                    this.invoices.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.invoices.loading = false;
                });
        },
        storeInvoice(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.invoices.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getInvoice(id) {
            this.invoice.loading = true;
            this.invoice.data = {};
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.invoice.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.invoice.loading = false;
                });
        },
        updateInvoice(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.invoices.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.invoices.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveInvoice(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.invoices.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.invoices.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        voidInvoice(id) {
            return apiAdmin(`${apiUrl}/${id}/void`, 'post')
                .then((res) => {
                    const index = this.invoices.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.invoices.data[index] = res.data.data;
                    }
                    if (this.invoice.data?.id === id) {
                        this.invoice.data = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteInvoice(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.invoices.data = this.invoices.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
