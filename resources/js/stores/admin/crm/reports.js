import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export const useCrmReportStore = defineStore('crmReport', {
    state: () => ({
        pipeline: null,
        conversion: null,
        followUps: null,
        tasks: null,
        loading: false,
    }),

    actions: {
        load(params = {}) {
            this.loading = true;
            const q = new URLSearchParams({ ...params }).toString();
            return Promise.all([
                apiAdmin(`crm/report/pipeline`).then((res) => { this.pipeline = res.data.data; }),
                apiAdmin(`crm/report/conversion?${q}`).then((res) => { this.conversion = res.data.data; }),
                apiAdmin(`crm/report/follow-ups`).then((res) => { this.followUps = res.data.data; }),
                apiAdmin(`crm/report/tasks`).then((res) => { this.tasks = res.data.data; }),
            ]).catch((err) => {
                showErrors(err);
            }).finally(() => {
                this.loading = false;
            });
        },
    },
});
