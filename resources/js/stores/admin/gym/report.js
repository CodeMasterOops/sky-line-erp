import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export const useGymReportStore = defineStore('gymReport', {
    state: () => ({
        report: {
            data: null,
            loading: false,
        },
    }),

    actions: {
        /**
         * @param {'membership-summary'|'renewals'|'revenue-by-plan'|'attendance'} name
         */
        load(name, { from = '', to = '' } = {}) {
            this.report.loading = true;
            const query = new URLSearchParams(
                Object.fromEntries(Object.entries({ from, to }).filter(([, v]) => v)),
            ).toString();

            return apiAdmin(`gym/report/${name}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.report.data = res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.report.loading = false;
                });
        },
    },
});
