import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'production-order';

export const useProductionOrderStore = defineStore('productionOrder', {
    state: () => ({
        orders: {
            data: [],
            meta: {},
            loading: false,
        },
        order: {
            data: {},
            loading: false,
        },
    }),

    actions: {
        getOrders({ filter }) {
            this.orders.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.orders.data = res.data.data;
                    this.orders.meta = {
                        total: res.data.total,
                        current_page: res.data.current_page,
                        per_page: res.data.per_page,
                        from: res.data.from,
                        to: res.data.to,
                        last_page: res.data.last_page,
                    };
                })
                .catch((err) => { showErrors(err); })
                .finally(() => { this.orders.loading = false; });
        },
        getOrder(id) {
            this.order.loading = true;
            this.order.data = {};
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => { this.order.data = res.data.data; })
                .catch((err) => { showErrors(err); })
                .finally(() => { this.order.loading = false; });
        },
        storeOrder(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.orders.data.unshift(res.data.data);
                    return res;
                })
                .catch((err) => { throw err; });
        },
        startOrder(id) {
            return apiAdmin(`${apiUrl}/${id}/start`, 'post')
                .then((res) => {
                    this._replaceInList(id, res.data.data);
                    return res;
                })
                .catch((err) => { throw err; });
        },
        completeOrder(id, form) {
            return apiAdmin(`${apiUrl}/${id}/complete`, 'post', form)
                .then((res) => {
                    this._replaceInList(id, res.data.data);
                    if (this.order.data?.id === id) {
                        this.order.data = res.data.data;
                    }
                    return res;
                })
                .catch((err) => { throw err; });
        },
        cancelOrder(id) {
            return apiAdmin(`${apiUrl}/${id}/cancel`, 'post')
                .then((res) => {
                    this._replaceInList(id, { ...this.orders.data.find(o => o.id === id), status: 'cancelled' });
                    return res;
                })
                .catch((err) => { throw err; });
        },
        startOperation(orderId, operationId) {
            return apiAdmin(`${apiUrl}/${orderId}/operations/${operationId}/start`, 'post')
                .then((res) => {
                    this._replaceOperation(orderId, res.data.data);
                    return res;
                })
                .catch((err) => { throw err; });
        },
        completeOperation(orderId, operationId, form) {
            return apiAdmin(`${apiUrl}/${orderId}/operations/${operationId}/complete`, 'post', form)
                .then((res) => {
                    this._replaceOperation(orderId, res.data.data);
                    return res;
                })
                .catch((err) => { throw err; });
        },
        skipOperation(orderId, operationId) {
            return apiAdmin(`${apiUrl}/${orderId}/operations/${operationId}/skip`, 'post')
                .then((res) => {
                    this._replaceOperation(orderId, res.data.data);
                    return res;
                })
                .catch((err) => { throw err; });
        },

        _replaceInList(id, updated) {
            const idx = this.orders.data.findIndex(o => o.id === id);
            if (idx !== -1) {
                this.orders.data[idx] = updated;
            }
        },
        _replaceOperation(orderId, updatedOp) {
            if (this.order.data?.id === orderId) {
                const ops = this.order.data.operations ?? [];
                const idx = ops.findIndex(o => o.id === updatedOp.id);
                if (idx !== -1) {
                    ops[idx] = updatedOp;
                }
            }
        },
    },
});
