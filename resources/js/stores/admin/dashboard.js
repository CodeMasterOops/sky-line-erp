import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

export const useAdminDashboardStore = defineStore('adminDashboard', {
    state: () => ({
        dashboard: {
            data: {
                total_sales:           0,
                total_sales_return:    0,
                total_purchase:        0,
                total_purchase_return: 0,
                customers_count:       0,
                suppliers_count:       0,
                products_count:        0,
                orders_today:          0,
                top_selling_products:  [],
                low_stock_products:    [],
                recent_transactions: {
                    invoices:   [],
                    bills:      [],
                    quotations: [],
                    expenses:   [],
                },
                top_customers: [],
                chart_data: {
                    labels:    [],
                    sales:     [],
                    purchases: [],
                    expenses:  [],
                },
                fiscal_year: {
                    start_date: null,
                    end_date:   null,
                },
            },
            loading: false,
        },
    }),
    actions: {
        getDashboardData({date_from, date_to} = {}) {
            this.dashboard.loading = true;
            const params = {};
            if (date_from) { params.date_from = date_from; }
            if (date_to)   { params.date_to   = date_to; }
            return apiAdmin('dashboard', 'get', params)
                .then((res) => {
                    this.dashboard.data = res.data;
                })
                .catch((err) => {
                    showErrors(err);
                })
                .finally(() => {
                    this.dashboard.loading = false;
                });
        },
    },
    getters: {
        isLoading: (state) => state.dashboard.loading,
        fiscalYear: (state) => state.dashboard.data.fiscal_year,
        summaryCards: (state) => {
            const d = state.dashboard.data;
            return [
                {label: 'Total Sales',           value: d.total_sales,           icon: 'ti-file-invoice',   color: 'primary'},
                {label: 'Total Sales Return',     value: d.total_sales_return,    icon: 'ti-receipt-refund', color: 'orange'},
                {label: 'Total Purchase',         value: d.total_purchase,        icon: 'ti-shopping-cart',  color: 'success'},
                {label: 'Total Purchase Return',  value: d.total_purchase_return, icon: 'ti-receipt-2',      color: 'info'},
            ];
        },
    },
});
