import {defineStore} from "pinia";
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'coupon'

export const useVendorCouponStore = defineStore('vendor-coupon', {
    state: () => ({
        coupons: {
            data: [],
            meta: {},
            loading: false
        },
        coupon: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getCoupons({filter}) {
            this.coupons.loading = true;
            return apiVendor(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.coupons.data = res.data.data;
                    this.coupons.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.coupons.loading = false;
                })
        },
        storeCoupon(form) {
            return apiVendor(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.coupons.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getCoupon(id) {
            this.coupon.loading = true;
            return apiVendor(`${apiUrl}/${id}`)
                .then((res) => {
                    this.coupon.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.coupon.loading = false;
                })
        },
        updateCoupon(id, form) {
            return apiVendor(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.coupons.data[this.coupons.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteCoupon(id) {
            return apiVendor(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.coupons.data = this.coupons.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiVendor(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.coupons.data[this.coupons.data.findIndex(d => d.id === id)].is_active = res.data.is_active;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
