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
        balanceSheet: {
            data: {
                period: null,
                fiscal_year: null,
                compare_fiscal_year: null,
                rows: [],
                summary: null,
            },
            loading: false,
        },
        profitLoss: {
            data: {
                period: null,
                fiscal_year: null,
                compare_fiscal_year: null,
                rows: [],
                summary: null,
            },
            loading: false,
        },
        journalReport: {
            data: {
                period: null,
                fiscal_year: null,
                journal_type: '',
                journal_type_options: [],
                rows: [],
                summary: null,
            },
            loading: false,
        },
        generalLedger: {
            data: {
                period: null,
                fiscal_year: null,
                selected_account: null,
                account_options: [],
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
        getBalanceSheet(filters = {}) {
            this.balanceSheet.loading = true;

            return apiAdmin(`${apiUrl}/balance-sheet`, 'get', filters)
                .then((res) => {
                    this.balanceSheet.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.balanceSheet.loading = false;
                });
        },
        getProfitLoss(filters = {}) {
            this.profitLoss.loading = true;

            return apiAdmin(`${apiUrl}/profit-loss`, 'get', filters)
                .then((res) => {
                    this.profitLoss.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.profitLoss.loading = false;
                });
        },
        getJournalReport(filters = {}) {
            this.journalReport.loading = true;

            return apiAdmin(`${apiUrl}/journal-report`, 'get', filters)
                .then((res) => {
                    this.journalReport.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.journalReport.loading = false;
                });
        },
        getGeneralLedger(filters = {}) {
            this.generalLedger.loading = true;

            return apiAdmin(`${apiUrl}/general-ledger`, 'get', filters)
                .then((res) => {
                    this.generalLedger.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.generalLedger.loading = false;
                });
        },
    }
})
