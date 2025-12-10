import {defineStore} from 'pinia'
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useSuperAdminDashboardStore = defineStore('super-admin-dashboard', {
    state: () => ({
        dashboard: {
            data: {},
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
    }
})
