import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";
import {useVendorAuthStore} from "@/stores/vendor/auth.js";

const apiUrl = 'vendor'

export const useVendorStore = defineStore('vendor', {
    state: () => ({
        vendors: {
            data: [],
            meta: {},
            loading: false
        },
        vendor: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getVendors({filter}) {
            this.vendors.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.vendors.data = res.data.data;
                    this.vendors.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.vendors.loading = false;
                })
        },
        storeVendor(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.vendors.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getVendor(id) {
            this.vendor.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.vendor.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.vendor.loading = false;
                })
        },
        updateVendor(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.vendors.data[this.vendors.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteVendor(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.vendors.data = this.vendors.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.vendors.data[this.vendors.data.findIndex(d => d.id === id)].is_active = res.data.is_active;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        resetPassword(id, form) {
            return apiAdmin(`${apiUrl}/${id}/reset-password`, 'put', form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        vendorLogin(id) {
            return apiAdmin(`vendor/${id}/login`, 'post')
                .then((res) => {
                    useVendorAuthStore().setAuthToken(res.data.access_token);
                    return res;
                }).catch((err) => {
                    console.log(err)
                    throw err;
                })
        },
        verify(id) {
            return apiAdmin(`vendor/${id}/verify`, 'post')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    console.log(err)
                    throw err;
                })
        },
        reject(id,reject_reason) {
            return apiAdmin(`vendor/${id}/reject`, 'post',{reject_reason})
                .then((res) => {
                    return res;
                }).catch((err) => {
                    console.log(err)
                    throw err;
                })
        },
    }
})
