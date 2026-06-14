import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'tax'

export const useTaxStore = defineStore('tax', {
    state: () => ({
        taxes: {
            data: [],
            meta: {},
            loading: false
        },
        tax: {
            data: {},
            loading: false
        },
        taxGroups: {
            data: [],
            loading: false
        },
        taxGroupsList: {
            data: [],
            meta: {},
            loading: false
        },
        taxGroupDetail: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getTaxes({ filter } = {}) {
            const params = {
                ...(filter ?? {}),
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.taxes.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.taxes.data = res.data.data;
                    this.taxes.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.taxes.loading = false;
                });
        },
        storeTax(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.taxes.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getTax(id) {
            this.tax.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.tax.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.tax.loading = false;
                })
        },
        updateTax(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.taxes.data[this.taxes.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteTax(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.taxes.data = this.taxes.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getTaxGroups() {
            this.taxGroups.loading = true;
            return apiAdmin('tax-group?active_only=true&limit=200')
                .then((res) => {
                    this.taxGroups.data = res.data.data ?? res.data ?? [];
                }).catch(showErrors).finally(() => {
                    this.taxGroups.loading = false;
                });
        },
        getTaxGroupsList({ filter } = {}) {
            const params = {
                active_only: false,
                ...(filter ?? {}),
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 10,
            };
            this.taxGroupsList.loading = true;
            return apiAdmin(`tax-group?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.taxGroupsList.data = res.data.data;
                    this.taxGroupsList.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.taxGroupsList.loading = false;
                });
        },
        storeTaxGroup(form) {
            return apiAdmin('tax-group', 'post', form)
                .then((res) => {
                    this.taxGroupsList.data.unshift(res.data.data);
                    return res;
                }).catch((err) => { throw err; });
        },
        getTaxGroupDetail(id) {
            this.taxGroupDetail.loading = true;
            return apiAdmin(`tax-group/${id}`)
                .then((res) => {
                    this.taxGroupDetail.data = res.data.data ?? res.data;
                }).catch(showErrors).finally(() => {
                    this.taxGroupDetail.loading = false;
                });
        },
        updateTaxGroup(id, form) {
            return apiAdmin(`tax-group/${id}`, 'put', form)
                .then((res) => {
                    const idx = this.taxGroupsList.data.findIndex(d => d.id === id);
                    if (idx !== -1) this.taxGroupsList.data[idx] = res.data.data;
                    return res;
                }).catch((err) => { throw err; });
        },
        deleteTaxGroup(id) {
            return apiAdmin(`tax-group/${id}`, 'delete')
                .then((res) => {
                    this.taxGroupsList.data = this.taxGroupsList.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => { throw err; });
        },
        calculateTaxGroup(baseAmount, taxGroupId, partyId = null, date = null) {
            const params = { base_amount: baseAmount, tax_group_id: taxGroupId };
            if (partyId) params.party_id = partyId;
            if (date) params.date = date;
            return apiAdmin('tax/calculate', 'post', params)
                .then((res) => res.data)
                .catch((err) => { throw err; });
        }
    }
})
