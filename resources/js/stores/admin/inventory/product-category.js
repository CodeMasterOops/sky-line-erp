import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'product-category'

export const useProductCategoryStore = defineStore('product-category', {
    state: () => ({
        productCategories: {
            data: [],
            loading: false
        },
        productCategory: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getProductCategories(refetch = false) {
            if (!this.productCategories.data.length || refetch) {
                this.productCategories.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.productCategories.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.productCategories.loading = false;
                    })
            }
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
