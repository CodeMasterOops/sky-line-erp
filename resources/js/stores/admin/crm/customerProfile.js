import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export const useCrmCustomerProfileStore = defineStore('crmCustomerProfile', {
    state: () => ({
        summary: {
            data: null,
            loading: false,
        },
        timeline: {
            data: [],
            meta: {},
            loading: false,
        },
    }),

    actions: {
        getSummary(partyId) {
            this.summary.loading = true;
            return apiAdmin(`crm/customer/${partyId}/summary`)
                .then((res) => {
                    this.summary.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.summary.loading = false;
                });
        },
        getTimeline(partyId, { page = 1, limit = 25, append = false } = {}) {
            this.timeline.loading = true;
            return apiAdmin(`crm/customer/${partyId}/timeline?page=${page}&limit=${limit}`)
                .then((res) => {
                    this.timeline.data = append
                        ? [...this.timeline.data, ...res.data.data]
                        : res.data.data;
                    this.timeline.meta = res.data.meta ?? {};
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.timeline.loading = false;
                });
        },
        resetTimeline() {
            this.timeline.data = [];
            this.timeline.meta = {};
        },
    },
});
