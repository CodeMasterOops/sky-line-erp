import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'sales-order';

export const useSalesOrderStore = defineStore('salesOrder', {
    state: () => ({
        orders: {
            data: [],
            meta: {},
            loading: false
        },
        order: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getOrders({filter}) {
            this.orders.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.orders.data = res.data.data;
                    this.orders.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.orders.loading = false;
                });
        },
        storeOrder(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.orders.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getOrder(id) {
            this.order.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.order.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.order.loading = false;
                });
        },
        updateOrder(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.orders.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.orders.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveOrder(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.orders.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.orders.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        convertToInvoice(id, payload) {
            return apiAdmin(`${apiUrl}/${id}/convert-to-invoice`, 'post', payload)
                .then((res) => {
                    const index = this.orders.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.orders.data[index] = {
                            ...this.orders.data[index],
                            invoice_count: (this.orders.data[index].invoice_count || 0) + 1,
                        };
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteOrder(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.orders.data = this.orders.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
