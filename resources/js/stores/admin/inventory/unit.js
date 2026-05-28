import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'unit'

export const useUnitStore = defineStore('unit', {
    state: () => ({
        units: {
            data: [],
            meta: {},
            loading: false
        },
        unit: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getUnits({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.units.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.units.data = res.data.data;
                    this.units.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.units.loading = false;
                });
        },
        storeUnit(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.units.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getUnit(id) {
            this.unit.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.unit.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.unit.loading = false;
                })
        },
        updateUnit(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.units.data[this.units.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteUnit(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.units.data = this.units.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
