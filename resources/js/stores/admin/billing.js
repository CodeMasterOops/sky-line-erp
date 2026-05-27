import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useBillingStore = defineStore('billing', {
    state: () => ({
        subscription: {
            data: null,
            loading: false,
        },
        plans: {
            data: [],
            loading: false,
        },
    }),

    actions: {
        getSubscription() {
            this.subscription.loading = true;

            return apiAdmin('billing/subscription')
                .then((res) => {
                    this.subscription.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.subscription.loading = false;
                });
        },
        getPlans() {
            this.plans.loading = true;

            return apiAdmin('billing/plans')
                .then((res) => {
                    this.plans.data = res.data?.data ?? res.data ?? [];
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.plans.loading = false;
                });
        },
        loadBillingPage() {
            return Promise.all([
                this.getSubscription(),
                this.getPlans(),
            ]);
        },
    },
});
