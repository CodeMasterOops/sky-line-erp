import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'gym/membership';

export const useMembershipStore = defineStore('gymMembership', {
    state: () => ({
        memberships: {
            data: [],
            meta: {},
            loading: false,
        },
        history: {
            data: [],
            loading: false,
        },
        expiring: {
            data: [],
            meta: {},
            loading: false,
        },
        dashboard: {
            data: null,
            loading: false,
        },
    }),

    actions: {
        getMemberships({ filter = {} } = {}) {
            this.memberships.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiAdmin(`${apiUrl}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.memberships.data = res.data.data;
                    this.memberships.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.memberships.loading = false;
                });
        },

        /** Every term a member has held, newest first. */
        getMemberHistory(memberId) {
            this.history.loading = true;

            return apiAdmin(`gym/member/${memberId}/membership`)
                .then((res) => {
                    this.history.data = res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.history.loading = false;
                });
        },

        getExpiring({ days = 7, limit = 25 } = {}) {
            this.expiring.loading = true;

            return apiAdmin(`${apiUrl}/expiring?days=${days}&limit=${limit}`)
                .then((res) => {
                    this.expiring.data = res.data.data;
                    this.expiring.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.expiring.loading = false;
                });
        },

        getDashboard({ days = 7 } = {}) {
            this.dashboard.loading = true;

            return apiAdmin(`gym/dashboard?days=${days}`)
                .then((res) => {
                    this.dashboard.data = res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.dashboard.loading = false;
                });
        },

        assign(form) {
            return apiAdmin(apiUrl, 'post', form);
        },

        renew(id, form = {}) {
            return apiAdmin(`${apiUrl}/${id}/renew`, 'post', form);
        },

        cancel(id, reason) {
            return apiAdmin(`${apiUrl}/${id}/cancel`, 'post', { reason });
        },
    },
});
