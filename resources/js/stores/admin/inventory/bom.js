import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'bom';

export const useBomStore = defineStore('bom', {
    state: () => ({
        boms: {
            data: [],
            meta: {},
            loading: false,
        },
        bom: {
            data: {},
            loading: false,
        },
    }),

    actions: {
        getBoms({ filter }) {
            this.boms.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.boms.data = res.data.data;
                    this.boms.meta = res.data.meta;
                })
                .catch((err) => { showErrors(err); })
                .finally(() => { this.boms.loading = false; });
        },
        getBom(id) {
            this.bom.loading = true;
            this.bom.data = {};
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => { this.bom.data = res.data.data; })
                .catch((err) => { showErrors(err); })
                .finally(() => { this.bom.loading = false; });
        },
        explodeBom(id, qty = 1) {
            return apiAdmin(`${apiUrl}/${id}/explode?qty=${qty}`)
                .then((res) => res.data.data);
        },
        whereUsed(variantId) {
            return apiAdmin(`${apiUrl}/where-used/${variantId}`)
                .then((res) => res.data.data);
        },
        planSubassemblies(bomId, { planned_qty, warehouse_id }) {
            return apiAdmin(`production-order/plan-subassemblies/${bomId}`, 'post', { planned_qty, warehouse_id })
                .then((res) => res.data);
        },
        storeBom(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.boms.data.unshift(res.data.data);
                    return res;
                })
                .catch((err) => { throw err; });
        },
        updateBom(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.boms.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.boms.data[index] = res.data.data;
                    }
                    return res;
                })
                .catch((err) => { throw err; });
        },
        deleteBom(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.boms.data = this.boms.data.filter(d => d.id !== id);
                    return res;
                })
                .catch((err) => { throw err; });
        },
        storeOperation(bomId, form) {
            return apiAdmin(`${apiUrl}/${bomId}/operations`, 'post', form)
                .then((res) => {
                    if (this.bom.data?.id === bomId) {
                        this.bom.data.operations = [
                            ...(this.bom.data.operations ?? []),
                            res.data.data,
                        ];
                    }
                    return res;
                })
                .catch((err) => { throw err; });
        },
        updateOperation(bomId, operationId, form) {
            return apiAdmin(`${apiUrl}/${bomId}/operations/${operationId}`, 'put', form)
                .then((res) => {
                    if (this.bom.data?.id === bomId) {
                        const ops = this.bom.data.operations ?? [];
                        const idx = ops.findIndex(o => o.id === operationId);
                        if (idx !== -1) {
                            ops[idx] = res.data.data;
                        }
                    }
                    return res;
                })
                .catch((err) => { throw err; });
        },
        deleteOperation(bomId, operationId) {
            return apiAdmin(`${apiUrl}/${bomId}/operations/${operationId}`, 'delete')
                .then((res) => {
                    if (this.bom.data?.id === bomId) {
                        this.bom.data.operations = (this.bom.data.operations ?? [])
                            .filter(o => o.id !== operationId);
                    }
                    return res;
                })
                .catch((err) => { throw err; });
        },
    },
});
