import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'advance-receipt';

export const useAdvanceReceiptStore = defineStore('advanceReceipt', {
    state: () => ({
        advances: {
            data: [],
            meta: {},
            loading: false,
        },
        advance: {
            data: {},
            loading: false,
        },
    }),

    actions: {
        getAdvances({ filter }) {
            this.advances.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.advances.data = res.data.data;
                    this.advances.meta = res.data.meta;
                })
                .catch((err) => {
                    showErrors(err);
                })
                .finally(() => {
                    this.advances.loading = false;
                });
        },

        storeAdvance(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.advances.data.unshift(res.data.data);
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        getAdvance(id) {
            this.advance.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.advance.data = res.data.data;
                })
                .catch((err) => {
                    showErrors(err);
                })
                .finally(() => {
                    this.advance.loading = false;
                });
        },

        updateAdvance(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.advances.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.advances.data[index] = res.data.data;
                    }
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        approveAdvance(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.advances.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.advances.data[index] = res.data.data;
                    }
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        adjustAdvance(id, form) {
            return apiAdmin(`${apiUrl}/${id}/adjust`, 'post', form)
                .then((res) => {
                    const index = this.advances.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.advances.data[index] = res.data.data;
                    }
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        voidAdvance(id) {
            return apiAdmin(`${apiUrl}/${id}/void`, 'post')
                .then((res) => {
                    this.advances.data = this.advances.data.filter((d) => d.id !== id);
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        deleteAdvance(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.advances.data = this.advances.data.filter((d) => d.id !== id);
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        getPartyBalance(partyId) {
            return apiAdmin(`advance-receipt-party-balance?party_id=${partyId}`)
                .then((res) => res.data)
                .catch((err) => {
                    showErrors(err);
                    return { balance: 0, advances: [] };
                });
        },
    },
});
