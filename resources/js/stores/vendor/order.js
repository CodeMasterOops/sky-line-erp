import {defineStore} from "pinia";
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'order'

export const useVendorOrderStore = defineStore('vendor-order', {
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
            return apiVendor(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
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
            return apiVendor(`${apiUrl}/${id}`)
                .then((res) => {
                    this.order.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.order.loading = false;
                })
        },
        updateStatus(id,form) {
            return apiVendor(`${apiUrl}/${id}/update-status`, 'put',form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        shippingLabel(id) {
            return apiVendor(`${apiUrl}/${id}/shipping-label`, 'post')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
