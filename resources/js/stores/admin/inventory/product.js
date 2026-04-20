import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
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
        },
        productVariants: {
            data: [],
            meta: {},
            loading: false
        },
    }),

    actions: {
        getProductVariants() {
            if (!this.productVariants.data.length) {
                this.productVariants.loading = true;
                return apiAdmin(`${apiUrl}/variant/all`)
                    .then((res) => {
                        this.productVariants.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.productVariants.loading = false;
                    });
            }
        },
        /**
         * Paginated variant search (name, product code, SKU). Does not cache the full catalog.
         */
        searchProductVariants({ q = '', barcode = '', page = 1, limit = 20 } = {}) {
            const params = new URLSearchParams();
            if (barcode) {
                params.set('barcode', barcode);
            }
            if (q) {
                params.set('q', q);
            }
            params.set('page', String(page));
            params.set('limit', String(limit));
            return apiAdmin(`${apiUrl}/variant/search?${params.toString()}`)
                .then((res) => res.data)
                .catch((err) => {
                    throw err;
                });
        },
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
