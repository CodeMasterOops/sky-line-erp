import {defineStore} from "pinia";
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'plan';

export const usePlanStore = defineStore('plan', {
    state: () => ({
        plans: {
            data: [],
            meta: {},
            loading: false,
        },
        plan: {
            data: {},
            loading: false,
        },
        stats: {
            total: 0,
            active: 0,
            inactive: 0,
            billingTypes: 0,
        },
    }),

    actions: {
        getPlans({filter = {}} = {}) {
            this.plans.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiSuperAdmin(`${apiUrl}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.plans.data = res.data.data;
                    this.plans.meta = res.data.meta || {};
                    this.updateStats();
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.plans.loading = false;
                });
        },
        storePlan(form) {
            return apiSuperAdmin(apiUrl, 'post', form)
                .then((res) => {
                    this.plans.data.push(res.data.data);
                    this.updateStats();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getPlan(id) {
            this.plan.loading = true;
            return apiSuperAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.plan.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.plan.loading = false;
                });
        },
        updatePlan(id, form) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.plans.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.plans.data[index] = res.data.data;
                    }
                    this.updateStats();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deletePlan(id) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.plans.data = this.plans.data.filter((d) => d.id !== id);
                    this.updateStats();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        updateStats() {
            const plans = this.plans.data;
            this.stats.total = this.plans.meta.total ?? plans.length;
            this.stats.active = plans.filter((plan) => plan.is_active).length;
            this.stats.inactive = plans.filter((plan) => !plan.is_active).length;
            this.stats.billingTypes = plans.filter(
                (plan) => Number(plan.price_monthly) > 0 || Number(plan.price_yearly) > 0
            ).length;
        },
    },
});
