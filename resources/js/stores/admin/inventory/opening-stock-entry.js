import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'opening-stock-entry';

export const useOpeningStockEntryStore = defineStore('openingStockEntry', {
    state: () => ({
        entries: {
            data: [],
            meta: {},
            loading: false
        },
        entry: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getEntries({filter}) {
            this.entries.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.entries.data = res.data.data;
                    this.entries.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.entries.loading = false;
                });
        },
        storeEntry(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.entries.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getEntry(id) {
            this.entry.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.entry.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.entry.loading = false;
                });
        },
        updateEntry(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.entries.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.entries.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveEntry(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.entries.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.entries.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteEntry(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.entries.data = this.entries.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
