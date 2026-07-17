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
         * Fetch the full variant catalog in one call (capped server-side).
         * Used to pre-populate grids such as the opening stock stocktake.
         */
        getAllProductVariants({ physical_only = 1, openable_only = 0, limit = 1000 } = {}) {
            const params = new URLSearchParams();
            if (physical_only) {
                params.set('physical_only', '1');
            }
            if (openable_only) {
                params.set('openable_only', '1');
            }
            params.set('limit', String(limit));
            return apiAdmin(`${apiUrl}/variant/all?${params.toString()}`)
                .then((res) => res.data.data ?? [])
                .catch((err) => {
                    throw err;
                });
        },
        /**
         * Paginated variant search (name, product code, SKU). Does not cache the full catalog.
         */
        searchProductVariants({ q = '', barcode = '', page = 1, limit = 20, physical_only = 0, batch_tracked_only = 0, category_id = null, with_stock = 0, item_roles = null, saleable_only = 0, purchasable_only = 0, openable_only = 0 } = {}) {
            const params = new URLSearchParams();
            if (barcode) {
                params.set('barcode', barcode);
            }
            if (openable_only) {
                params.set('openable_only', '1');
            }
            if (item_roles) {
                params.set('item_roles', item_roles);
            }
            if (saleable_only) {
                params.set('saleable_only', '1');
            }
            if (purchasable_only) {
                params.set('purchasable_only', '1');
            }
            if (q) {
                params.set('q', q);
            }
            if (physical_only) {
                params.set('physical_only', '1');
            }
            if (batch_tracked_only) {
                params.set('batch_tracked_only', '1');
            }
            if (with_stock) {
                params.set('with_stock', '1');
            }
            if (category_id) {
                params.set('category_id', String(category_id));
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
