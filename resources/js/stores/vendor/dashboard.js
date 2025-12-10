import {defineStore} from 'pinia'
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useVendorDashboardStore = defineStore('vendor-dashboard', {
    state: () => ({
        dashboard: {
            data: {},
            loading: false
        }
    }),
    actions: {
        getDashboardData() {
            this.dashboard.loading = true;
            return apiVendor(`dashboard`)
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
