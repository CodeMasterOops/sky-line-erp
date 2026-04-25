import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";

const apiUrl = 'account-report';

export const useAccountingReportStore = defineStore('accounting-report', {
    state: () => ({
        trialBalance: { data: { period: null, fiscal_year: null, rows: [], summary: null }, loading: false },
        balanceSheet: { data: { period: null, fiscal_year: null, compare_fiscal_year: null, rows: [], summary: null }, loading: false },
        profitLoss: { data: { period: null, fiscal_year: null, compare_fiscal_year: null, rows: [], summary: null }, loading: false },
        journalReport: { data: { period: null, fiscal_year: null, journal_type: '', journal_type_options: [], rows: [], summary: null }, loading: false },
        generalLedger: { data: { period: null, fiscal_year: null, selected_account: null, account_options: [], rows: [], summary: null }, loading: false },
        cashFlow: { data: { period: null, fiscal_year: null, operating: 0, investing: 0, financing: 0, net_change: 0 }, loading: false },
        vatReturn: { data: { period: null, fiscal_year: null, sales: null, purchases: null, net_vat_payable: 0, sales_rows: [], purchase_rows: [] }, loading: false },
        vatSalesRegister: { data: { period: null, fiscal_year: null, rows: [], summary: null }, loading: false },
        vatPurchaseRegister: { data: { period: null, fiscal_year: null, rows: [], summary: null }, loading: false },
        tdsReport: { data: { period: null, fiscal_year: null, rows: [], summary: null }, loading: false },
        arAging: { data: { as_of: null, rows: [], buckets: null }, loading: false },
        apAging: { data: { as_of: null, rows: [], buckets: null }, loading: false },
        inventoryValuation: { data: { rows: [], summary: null }, loading: false },
        stockAging: { data: { as_of: null, rows: [], buckets: null }, loading: false },
        reorderAlerts: { data: { rows: [], count: 0 }, loading: false },
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
        getCashFlow(filters = {}) {
            this.cashFlow.loading = true;
            return apiAdmin(`${apiUrl}/cash-flow`, 'get', filters)
                .then((res) => { this.cashFlow.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.cashFlow.loading = false; });
        },
        getVatReturn(filters = {}) {
            this.vatReturn.loading = true;
            return apiAdmin(`${apiUrl}/vat-return`, 'get', filters)
                .then((res) => { this.vatReturn.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.vatReturn.loading = false; });
        },
        getVatSalesRegister(filters = {}) {
            this.vatSalesRegister.loading = true;
            return apiAdmin(`${apiUrl}/vat-sales-register`, 'get', filters)
                .then((res) => { this.vatSalesRegister.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.vatSalesRegister.loading = false; });
        },
        getVatPurchaseRegister(filters = {}) {
            this.vatPurchaseRegister.loading = true;
            return apiAdmin(`${apiUrl}/vat-purchase-register`, 'get', filters)
                .then((res) => { this.vatPurchaseRegister.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.vatPurchaseRegister.loading = false; });
        },
        getTdsReport(filters = {}) {
            this.tdsReport.loading = true;
            return apiAdmin(`${apiUrl}/tds-report`, 'get', filters)
                .then((res) => { this.tdsReport.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.tdsReport.loading = false; });
        },
        getArAging(filters = {}) {
            this.arAging.loading = true;
            return apiAdmin(`${apiUrl}/ar-aging`, 'get', filters)
                .then((res) => { this.arAging.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.arAging.loading = false; });
        },
        getApAging(filters = {}) {
            this.apAging.loading = true;
            return apiAdmin(`${apiUrl}/ap-aging`, 'get', filters)
                .then((res) => { this.apAging.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.apAging.loading = false; });
        },
        getInventoryValuation(filters = {}) {
            this.inventoryValuation.loading = true;
            return apiAdmin(`${apiUrl}/inventory-valuation`, 'get', filters)
                .then((res) => { this.inventoryValuation.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.inventoryValuation.loading = false; });
        },
        getStockAging(filters = {}) {
            this.stockAging.loading = true;
            return apiAdmin(`${apiUrl}/stock-aging`, 'get', filters)
                .then((res) => { this.stockAging.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.stockAging.loading = false; });
        },
        getReorderAlerts(filters = {}) {
            this.reorderAlerts.loading = true;
            return apiAdmin(`${apiUrl}/reorder-alerts`, 'get', filters)
                .then((res) => { this.reorderAlerts.data = res.data.data; return res; })
                .catch((err) => { showErrors(err); throw err; })
                .finally(() => { this.reorderAlerts.loading = false; });
        },
    }
})
