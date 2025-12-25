import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const unitUrl = 'unit'

export const useUnitStore = defineStore('unit', {
    state: () => ({
        units: {
            data: [],
            loading: false
        },
        unit: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getUnits() {
            if (!this.units.data.length) {
                this.units.loading = true;
                return apiAdmin(`${unitUrl}`)
                    .then((res) => {
                        this.units.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.units.loading = false;
                    })
            }
        },
        storeUnit(form) {
            return apiAdmin(`${unitUrl}`, 'post', form)
                .then((res) => {
                    this.units.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getUnit(id) {
            this.unit.loading = true;
            return apiAdmin(`${unitUrl}/${id}`)
                .then((res) => {
                    this.unit.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.unit.loading = false;
                })
        },
        updateUnit(id, form) {
            return apiAdmin(`${unitUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.units.data[this.units.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteUnit(id) {
            return apiAdmin(`${unitUrl}/${id}`, 'delete')
                .then((res) => {
                    this.units.data = this.units.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
