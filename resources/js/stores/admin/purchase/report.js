import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'purchase-report';

export const usePurchaseReportStore = defineStore('purchase-report', {
    state: () => ({
        dashboard: {
            data: {
                period: null,
                summary: {
                    total_amount: 0,
                    total_paid: 0,
                    total_unpaid: 0,
                    overdue_amount: 0,
                    total_bills: 0,
                },
            },
            loading: false,
        },
        purchaseReport: {
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
                    total_bills: 0,
                },
            },
            loading: false,
        },
        purchaseByItem: {
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
        getPurchaseReport(filters = {}) {
            this.purchaseReport.loading = true;

            return apiAdmin(`${apiUrl}/report`, 'get', filters)
                .then((res) => {
                    this.purchaseReport.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.purchaseReport.loading = false;
                });
        },
        getPurchaseByItem(filters = {}) {
            this.purchaseByItem.loading = true;

            return apiAdmin(`${apiUrl}/purchase-by-item`, 'get', filters)
                .then((res) => {
                    this.purchaseByItem.data = res.data.data;
                    return res;
                }).catch((err) => {
                    showErrors(err);
                    throw err;
                }).finally(() => {
                    this.purchaseByItem.loading = false;
                });
        },
    },
});
