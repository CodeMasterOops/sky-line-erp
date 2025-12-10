import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useAdminDashboardStore = defineStore('adminDashboard', {
    state: () => ({
        dashboard: {
            data: {},
            loading: false
        }
    }),
    actions: {
        getDashboardData() {
            this.dashboard.loading = true;
            return apiAdmin(`dashboard`)
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
