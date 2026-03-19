import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'account-group'

export const useAccountGroupStore = defineStore('account-group', {
    state: () => ({
        accountGroups: {
            data: [],
            loading: false
        },
        accountGroup: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAccountGroups() {
            if (!this.accountGroups.data.length) {
                this.accountGroups.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.accountGroups.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.accountGroups.loading = false;
                    })
            }
        },
        storeAccountGroup(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.accountGroups.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getAccountGroup(id) {
            this.accountGroup.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.accountGroup.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.accountGroup.loading = false;
                })
        },
        updateAccountGroup(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.accountGroups.data[this.accountGroups.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteAccountGroup(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.accountGroups.data = this.accountGroups.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
