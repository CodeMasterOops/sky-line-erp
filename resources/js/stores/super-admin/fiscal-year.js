import {defineStore} from "pinia";
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'fiscal-year'

export const useFiscalYearStore = defineStore('fiscal-year', {
    state: () => ({
        fiscalYears: {
            data: [],
            meta: {},
            loading: false
        },
        fiscalYear: {
            data: {},
            loading: false
        },
        currentYear: {},
    }),

    actions: {
        getFiscalYears({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.fiscalYears.loading = true;
            return apiSuperAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.fiscalYears.data = res.data.data;
                    this.fiscalYears.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.fiscalYears.loading = false;
                });
        },
        storeFiscalYear(form) {
            return apiSuperAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.fiscalYears.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getFiscalYear(id) {
            this.fiscalYear.loading = true;
            return apiSuperAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.fiscalYear.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.fiscalYear.loading = false;
                })
        },
        updateFiscalYear(id, form) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.fiscalYears.data[this.fiscalYears.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteFiscalYear(id) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.fiscalYears.data = this.fiscalYears.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        setCurrentFiscalYear(id) {
            return apiSuperAdmin(`${apiUrl}/${id}/set-current`, 'post')
                .then((res) => {
                    this.fiscalYears.data = this.fiscalYears.data.map(fy => ({
                        ...fy,
                        is_current: fy.id === id,
                    }));
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
