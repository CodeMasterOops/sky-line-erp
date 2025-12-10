import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'order'

export const useOrderStore = defineStore('order', {
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
                })
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
                })
        },
        deleteOrder(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.orders.data = this.orders.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id,form) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put',form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        shippingLabel(id) {
            return apiAdmin(`${apiUrl}/${id}/shipping-label`, 'post')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
