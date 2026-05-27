import {defineStore} from 'pinia'
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useSuperAdminDashboardStore = defineStore('super-admin-dashboard', {
    state: () => ({
        dashboard: {
            data: {
                total_companies: 0,
                active_companies: 0,
                inactive_companies: 0,
                onboarded_companies: 0,
                companies_today: 0,
                total_users: 0,
                fiscal_years_count: 0,
                total_earnings: 0,
                growth: {
                    total_companies: 0,
                    active_companies: 0,
                    onboarded_companies: 0,
                    total_earnings: 0,
                    new_companies: 0,
                },
                companies_from_last_month: 0,
                chart_data: {
                    weekly: {labels: [], companies: []},
                    monthly: {labels: [], new_companies: [], active_companies: []},
                    sparklines: {total: [], active: [], onboarded: [], earnings: []},
                },
                top_plans: [],
            },
            loading: false
        }
    }),
    actions: {
        getDashboardData() {
            this.dashboard.loading = true;
            return apiSuperAdmin(`dashboard`)
                .then((res) => {
                    this.dashboard.data = res.data;
                })
                .catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.dashboard.loading = false;
                });
        },
    },
    getters: {
        isLoading: (state) => state.dashboard.loading,
    },
})
