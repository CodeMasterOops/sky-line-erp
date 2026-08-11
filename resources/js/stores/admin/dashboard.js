import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

/**
 * The server sends only the widgets the company's modules support, and names
 * them in `widgets`. A key that is absent means "your company does not run
 * this", which is NOT the same as a key present and zero — never fill an
 * absent widget with a default, or a company without Purchase reads
 * "Total Purchase 0.00" instead of seeing nothing at all.
 *
 * @see app/Http/Controllers/Api/Admin/DashboardController.php — WIDGETS
 */
const emptyDashboard = () => ({
    widgets: [],
    recent_transactions: {},
    chart_data: {
        labels: [],
    },
    fiscal_year: {
        start_date: null,
        end_date:   null,
    },
});

export const useAdminDashboardStore = defineStore('adminDashboard', {
    state: () => ({
        dashboard: {
            data: emptyDashboard(),
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
                    this.dashboard.data = {...emptyDashboard(), ...res.data};
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
        /** Whether the server computed the given widget for this company. */
        hasWidget: (state) => (widget) =>
            Array.isArray(state.dashboard.data.widgets)
            && state.dashboard.data.widgets.includes(widget),
        summaryCards: (state) => {
            const d = state.dashboard.data;
            const widgets = Array.isArray(d.widgets) ? d.widgets : [];
            const cards = [];

            if (widgets.includes('sales_totals')) {
                cards.push(
                    {label: 'Total Sales',          value: d.total_sales,        icon: 'ti-file-invoice',   color: 'primary'},
                    {label: 'Total Sales Return',   value: d.total_sales_return, icon: 'ti-receipt-refund', color: 'orange'},
                );
            }

            if (widgets.includes('purchase_totals')) {
                cards.push(
                    {label: 'Total Purchase',        value: d.total_purchase,        icon: 'ti-shopping-cart', color: 'success'},
                    {label: 'Total Purchase Return', value: d.total_purchase_return, icon: 'ti-receipt-2',     color: 'info'},
                );
            }

            return cards;
        },
    },
});
