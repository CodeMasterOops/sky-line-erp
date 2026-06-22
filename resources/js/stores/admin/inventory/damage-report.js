import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'damage-report';

export const useDamageReportStore = defineStore('damageReport', {
    state: () => ({
        reports: {
            data: [],
            meta: {},
            loading: false,
        },
        report: {
            data: {},
            loading: false,
        },
    }),

    actions: {
        getReports({filter}) {
            this.reports.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.reports.data = res.data.data;
                    this.reports.meta = res.data.meta;
                })
                .catch((err) => {
                    showErrors(err);
                })
                .finally(() => {
                    this.reports.loading = false;
                });
        },

        storeReport(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.reports.data.unshift(res.data.data);
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        getReport(id) {
            this.report.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.report.data = res.data.data;
                })
                .catch((err) => {
                    showErrors(err);
                })
                .finally(() => {
                    this.report.loading = false;
                });
        },

        updateReport(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.reports.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.reports.data[index] = res.data.data;
                    }
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        approveReport(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.reports.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.reports.data[index] = res.data.data;
                    }
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },

        deleteReport(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.reports.data = this.reports.data.filter((d) => d.id !== id);
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
    },
});
