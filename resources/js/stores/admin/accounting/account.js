import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'account'

export const useAccountStore = defineStore('account', {
    state: () => ({
        accounts: {
            data: [],
            meta: {},
            loading: false
        },
        coaTree: {
            data: [],
            loading: false
        },
        account: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAccounts({ filter } = {}) {
            // Dropdowns call without a filter and must never be truncated, so
            // request the full set. The paginated CoA list passes a filter.
            const params = filter
                ? {
                    page: filter.page ?? 1,
                    limit: filter.limit ?? 25,
                    ...(filter.search ? { search: filter.search } : {}),
                    ...(filter.category ? { category: filter.category } : {}),
                    ...(filter.account_group_id ? { account_group_id: filter.account_group_id } : {}),
                }
                : { all: 1 };
            this.accounts.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.accounts.data = res.data.data;
                    this.accounts.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.accounts.loading = false;
                });
        },
        getCoaTree() {
            this.coaTree.loading = true;
            return apiAdmin(`${apiUrl}/coa`)
                .then((res) => {
                    this.coaTree.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.coaTree.loading = false;
                })
        },
        storeAccount(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.accounts.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getAccount(id) {
            this.account.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.account.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.account.loading = false;
                })
        },
        updateAccount(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.accounts.data[this.accounts.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteAccount(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.accounts.data = this.accounts.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
