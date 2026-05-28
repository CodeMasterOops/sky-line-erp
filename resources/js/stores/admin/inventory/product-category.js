import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'product-category'

export const useProductCategoryStore = defineStore('product-category', {
    state: () => ({
        productCategories: {
            data: [],
            meta: {},
            loading: false
        },
        productCategory: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getProductCategories({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.productCategories.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.productCategories.data = res.data.data;
                    this.productCategories.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.productCategories.loading = false;
                });
        },
        storeProductCategory(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.productCategories.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getProductCategory(id) {
            this.productCategory.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.productCategory.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.productCategory.loading = false;
                })
        },
        updateProductCategory(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.productCategories.data[this.productCategories.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteProductCategory(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.productCategories.data = this.productCategories.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
