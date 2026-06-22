import {defineStore} from "pinia";
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'company'

export const useCompanyStore = defineStore('company', {
    state: () => ({
        companies: {
            data: [],
            meta: {},
            loading: false
        },
        company: {
            data: {},
            meta: {},
            loading: false
        }
    }),

    actions: {
        getCompanies({filter}) {
            this.companies.loading = true;
            return apiSuperAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.companies.data = res.data.data;
                    this.companies.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.companies.loading = false;
                })
        },
        storeCompany(form) {
            return apiSuperAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.companies.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getCompany(id) {
            this.company.loading = true;
            return apiSuperAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.company.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.company.loading = false;
                })
        },
        updateCompany(id, form) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.companies.data[this.companies.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteCompany(id) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.companies.data = this.companies.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiSuperAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.companies.data[this.companies.data.findIndex(d => d.id === id)].is_active = res.data.is_active;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        resetPassword(id, form) {
            return apiSuperAdmin(`${apiUrl}/${id}/reset-password`, 'put', form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getBranches(companyId) {
            return apiSuperAdmin(`${apiUrl}/${companyId}/branch`)
                .then((res) => res.data.data)
                .catch((err) => {
                    throw err;
                })
        },
        storeBranch(companyId, form) {
            return apiSuperAdmin(`${apiUrl}/${companyId}/branch`, 'post', form)
                .catch((err) => {
                    throw err;
                })
        },
        updateBranch(companyId, branchId, form) {
            return apiSuperAdmin(`${apiUrl}/${companyId}/branch/${branchId}`, 'put', form)
                .catch((err) => {
                    throw err;
                })
        },
        deleteBranch(companyId, branchId) {
            return apiSuperAdmin(`${apiUrl}/${companyId}/branch/${branchId}`, 'delete')
                .catch((err) => {
                    throw err;
                })
        }
    }
})
