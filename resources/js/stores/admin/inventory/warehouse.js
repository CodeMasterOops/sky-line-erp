import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'warehouse'

export const useWarehouseStore = defineStore('warehouse', {
    state: () => ({
        warehouses: {
            data: [],
            loading: false
        },
        warehouse: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getWarehouses() {
            if (!this.warehouses.data.length) {
                this.warehouses.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.warehouses.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.warehouses.loading = false;
                    })
            }
        },
        storeWarehouse(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.warehouses.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getWarehouse(id) {
            this.warehouse.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.warehouse.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.warehouse.loading = false;
                })
        },
        updateWarehouse(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.warehouses.data[this.warehouses.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteWarehouse(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.warehouses.data = this.warehouses.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
