import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'gym/check-in';

export const useCheckInStore = defineStore('gymCheckIn', {
    state: () => ({
        checkIns: {
            data: [],
            meta: {},
            loading: false,
        },
        lookup: {
            data: null,
            loading: false,
            notFound: false,
        },
    }),

    actions: {
        getCheckIns({ filter = {} } = {}) {
            this.checkIns.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiAdmin(`${apiUrl}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.checkIns.data = res.data.data;
                    this.checkIns.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.checkIns.loading = false;
                });
        },

        /** Member ID or phone — whatever the front desk types. */
        findMember(identifier) {
            this.lookup.loading = true;
            this.lookup.notFound = false;

            return apiAdmin(`${apiUrl}/lookup`, 'post', { identifier })
                .then((res) => {
                    this.lookup.data = res.data.data;
                    return res.data.data;
                })
                .catch((err) => {
                    if (err?.response?.status === 404) {
                        this.lookup.data = null;
                        this.lookup.notFound = true;
                        return null;
                    }
                    showErrors(err);
                    return null;
                })
                .finally(() => {
                    this.lookup.loading = false;
                });
        },

        checkIn(memberId) {
            return apiAdmin(apiUrl, 'post', { member_id: memberId });
        },

        checkOut(id) {
            return apiAdmin(`${apiUrl}/${id}/check-out`, 'post');
        },

        clearLookup() {
            this.lookup.data = null;
            this.lookup.notFound = false;
        },
    },
});
