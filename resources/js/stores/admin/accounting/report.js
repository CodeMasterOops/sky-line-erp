import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'account-report';

export const useAccountingReportStore = defineStore('accounting-report', {
    state: () => ({
        trialBalance: {
            data: {
                period: null,
                fiscal_year: null,
                rows: [],
                summary: null,
            },
            loading: false,
        },
    }),

    actions: {
        getTrialBalance(filters = {}) {
            this.trialBalance.loading = true;

            return apiAdmin(`${apiUrl}/trial-balance`, 'get', filters)
                .then((res) => {
                    this.trialBalance.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.trialBalance.loading = false;
                });
        },
    }
})
