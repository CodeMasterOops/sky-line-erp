import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'sales-report';

export const useSalesReportStore = defineStore('sales-report', {
    state: () => ({
        dashboard: {
            data: {
                period: null,
                summary: {
                    total_amount: 0,
                    total_paid: 0,
                    total_unpaid: 0,
                    overdue_amount: 0,
                    total_invoices: 0,
                },
            },
            loading: false,
        },
        salesReport: {
            data: {
                period: null,
                selected_party_id: null,
                selected_product_variant_id: null,
                party_options: [],
                product_variant_options: [],
                rows: [],
                summary: {
                    total_amount: 0,
                    total_paid: 0,
                    total_unpaid: 0,
                    overdue_amount: 0,
                    total_invoices: 0,
                },
            },
            loading: false,
        },
        salesByItem: {
            data: {
                period: null,
                selected_product_variant_id: null,
                product_variant_options: [],
                rows: [],
                summary: {
                    quantity: 0,
                    amount: 0,
                    discount: 0,
                    net_sales: 0,
                    vat_amount: 0,
                    total_amount: 0,
                },
            },
            loading: false,
        },
        aging: {
            data: { as_of: null, rows: [], buckets: { current: 0, '1_30': 0, '31_60': 0, '61_90': 0, over_90: 0, total: 0 } },
            loading: false,
        },
        partyStatement: {
            data: { party: null, period: null, rows: [], summary: { total_invoiced: 0, total_received: 0, closing_balance: 0 } },
            loading: false,
        },
        vatRegister: {
            data: { period: null, taxable_sales: [], exempt_sales: [], zero_rated_sales: [], totals: { taxable: 0, vat: 0, exempt: 0, zero_rated: 0 } },
            loading: false,
        },
        tdsRegister: {
            data: { period: null, rows: [], by_party: [], summary: { total_base_amount: 0, total_tds_amount: 0 } },
            loading: false,
        },
        outstanding: {
            data: { as_of: null, rows: [], summary: { total_invoiced: 0, total_received: 0, tds_deducted: 0, net_outstanding: 0 } },
            loading: false,
        },
    }),

    actions: {
        getDashboard(filters = {}) {
            this.dashboard.loading = true;

            return apiAdmin(`${apiUrl}/dashboard`, 'get', filters)
                .then((res) => {
                    this.dashboard.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.dashboard.loading = false;
                });
        },
        getSalesReport(filters = {}) {
            this.salesReport.loading = true;

            return apiAdmin(`${apiUrl}/report`, 'get', filters)
                .then((res) => {
                    this.salesReport.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.salesReport.loading = false;
                });
        },
        getSalesByItem(filters = {}) {
            this.salesByItem.loading = true;

            return apiAdmin(`${apiUrl}/sales-by-item`, 'get', filters)
                .then((res) => {
                    this.salesByItem.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.salesByItem.loading = false;
                });
        },
        getAging(filters = {}) {
            this.aging.loading = true;

            return apiAdmin(`${apiUrl}/aging`, 'get', filters)
                .then((res) => {
                    this.aging.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.aging.loading = false;
                });
        },
        getPartyStatement(filters = {}) {
            this.partyStatement.loading = true;

            return apiAdmin(`${apiUrl}/party-statement`, 'get', filters)
                .then((res) => {
                    this.partyStatement.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.partyStatement.loading = false;
                });
        },
        getVatRegister(filters = {}) {
            this.vatRegister.loading = true;

            return apiAdmin(`${apiUrl}/vat-register`, 'get', filters)
                .then((res) => {
                    this.vatRegister.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.vatRegister.loading = false;
                });
        },
        getTdsRegister(filters = {}) {
            this.tdsRegister.loading = true;

            return apiAdmin(`${apiUrl}/tds-register`, 'get', filters)
                .then((res) => {
                    this.tdsRegister.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.tdsRegister.loading = false;
                });
        },
        getOutstanding(filters = {}) {
            this.outstanding.loading = true;

            return apiAdmin(`${apiUrl}/outstanding`, 'get', filters)
                .then((res) => {
                    this.outstanding.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.outstanding.loading = false;
                });
        },
    },
});
