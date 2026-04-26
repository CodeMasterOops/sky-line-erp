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
            },
            loading: false,
        },
    }),
    actions: {
        getDashboardData() {
            this.dashboard.loading = true;
            return apiAdmin('dashboard')
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
        summaryCards: (state) => {
            const d = state.dashboard.data;
            return [
                {label: 'Total Sales',           value: d.total_sales,           icon: 'ti-file-text',     color: 'primary'},
                {label: 'Total Sales Return',     value: d.total_sales_return,    icon: 'ti-repeat',        color: 'secondary'},
                {label: 'Total Purchase',         value: d.total_purchase,        icon: 'ti-gift',          color: 'teal'},
                {label: 'Total Purchase Return',  value: d.total_purchase_return, icon: 'ti-brand-pocket',  color: 'info'},
            ];
        },
    },
});
