import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'brand'

export const useBrandStore = defineStore('brand', {
    state: () => ({
        brands: {
            data: [],
            loading: false
        },
        brand: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getBrands() {
            if (!this.brands.data.length) {
                this.brands.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.brands.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.brands.loading = false;
                    })
            }
        },
        storeBrand(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.brands.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getBrand(id) {
            this.brand.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.brand.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.brand.loading = false;
                })
        },
        updateBrand(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.brands.data[this.brands.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteBrand(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.brands.data = this.brands.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
