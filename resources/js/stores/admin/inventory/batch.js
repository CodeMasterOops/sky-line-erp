import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'batch';

export const useBatchStore = defineStore('batch', {
    state: () => ({
        batches: {
            data: [],
            meta: {},
            loading: false,
        },
        batch: {
            data: {},
            loading: false,
        },
    }),

    actions: {
        getBatches({filter}) {
            this.batches.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.batches.data = res.data.data;
                    this.batches.meta = res.data.meta;
                })
                .catch((err) => {
                    showErrors(err);
                })
                .finally(() => {
                    this.batches.loading = false;
                });
        },

        storeBatch(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.batches.data.unshift(res.data.data);
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        getBatch(id) {
            this.batch.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.batch.data = res.data.data;
                })
                .catch((err) => {
                    showErrors(err);
                })
                .finally(() => {
                    this.batch.loading = false;
                });
        },

        updateBatch(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.batches.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.batches.data[index] = res.data.data;
                    }
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
    },
});
