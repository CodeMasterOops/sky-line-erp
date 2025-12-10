import {defineStore} from "pinia";
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'product'

export const useVendorProductStore = defineStore('vendor-product', {
    state: () => ({
        allProducts: {
            data: [],
            loading: false
        },
        products: {
            data: [],
            meta: {},
            loading: false
        },
        product: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAllProducts() {
            if(!this.allProducts.data.length){
                this.allProducts.loading = true;
                return apiVendor(`${apiUrl}/list/all`)
                    .then((res) => {
                        this.allProducts.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.allProducts.loading = false;
                    })
            }
        },
        getProducts({filter}) {
            this.products.loading = true;
            return apiVendor(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.products.data = res.data.data;
                    this.products.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.products.loading = false;
                })
        },
        storeProduct(form) {
            return apiVendor(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.products.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getProduct(id) {
            this.product.loading = true;
            return apiVendor(`${apiUrl}/${id}`)
                .then((res) => {
                    this.product.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.product.loading = false;
                })
        },
        updateProduct(id, form) {
            return apiVendor(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.products.data[this.products.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteProduct(id) {
            return apiVendor(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.products.data = this.products.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiVendor(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.products.data[this.products.data.findIndex(d => d.id === id)].is_active = res.data.is_active;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
