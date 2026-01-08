import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'product';

export const useProductStore = defineStore('product', {
    state: () => ({
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
        getProducts({filter}) {
            this.products.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.products.data = res.data.data;
                    this.products.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.products.loading = false;
                });
        },
        storeProduct(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.products.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getProduct(id) {
            this.product.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.product.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.product.loading = false;
                });
        },
        updateProduct(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.products.data[this.products.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteProduct(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.products.data = this.products.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
