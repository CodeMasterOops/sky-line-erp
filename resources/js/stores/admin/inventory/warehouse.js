import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";
import {buildWarehouseOptionsTree} from "@/helpers/warehouseTree.js";

const apiUrl = 'warehouse'

export const useWarehouseStore = defineStore('warehouse', {
    state: () => ({
        warehouses: {
            data: [],
            meta: {},
            loading: false
        },
        warehouse: {
            data: {},
            loading: false
        }
    }),

    getters: {
        optionsTree(state) {
            return buildWarehouseOptionsTree(state.warehouses.data);
        },
        optionsTreeExcluding: (state) => (excludeIds = new Set()) =>
            buildWarehouseOptionsTree(state.warehouses.data, excludeIds),
    },

    actions: {
        getWarehouses({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.warehouses.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.warehouses.data = res.data.data;
                    this.warehouses.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.warehouses.loading = false;
                });
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
