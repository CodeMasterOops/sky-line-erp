import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'product-category'

export const useProductCategoryStore = defineStore('productCategory', {
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
        getProductCategories(refreshData=false) {
            if(refreshData || !this.productCategories.data.length){
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
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteProductCategory(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.getProductCategories(true);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateFeaturedStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-featured-status`, 'put')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
