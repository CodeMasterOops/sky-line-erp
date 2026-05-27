import {defineStore} from "pinia";
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'subscription';

export const useSubscriptionStore = defineStore('subscription', {
    state: () => ({
        subscriptions: {
            data: [],
            meta: {},
            loading: false,
        },
        subscription: {
            data: {},
            loading: false,
        },
        summary: {
            totalRevenue: 0,
            totalSubscribers: 0,
            activeSubscribers: 0,
            expiredSubscribers: 0,
        },
    }),

    actions: {
        getSubscriptions({filter = {}} = {}) {
            this.subscriptions.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiSuperAdmin(`${apiUrl}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.subscriptions.data = res.data.data;
                    this.subscriptions.meta = res.data.meta || {};
                    this.updateSummary();
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.subscriptions.loading = false;
                });
        },
        assignSubscription(form) {
            return apiSuperAdmin(apiUrl, 'post', form)
                .then((res) => {
                    this.subscriptions.data.unshift(res.data.data);
                    this.updateSummary();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getSubscription(id) {
            this.subscription.loading = true;
            return apiSuperAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.subscription.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.subscription.loading = false;
                });
        },
        updateSubscription(id, form) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.subscriptions.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.subscriptions.data[index] = res.data.data;
                    }
                    this.updateSummary();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        cancelSubscription(id, form = {}) {
            return apiSuperAdmin(`${apiUrl}/${id}/cancel`, 'put', form)
                .then((res) => {
                    const index = this.subscriptions.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.subscriptions.data[index] = res.data.data;
                    }
                    this.updateSummary();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        renewSubscription(id, form = {}) {
            return apiSuperAdmin(`${apiUrl}/${id}/renew`, 'put', form)
                .then((res) => {
                    const index = this.subscriptions.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.subscriptions.data[index] = res.data.data;
                    }
                    this.updateSummary();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        updateSummary() {
            const rows = this.subscriptions.data;
            const activeStatuses = ['active', 'trialing'];

            this.summary.totalSubscribers = this.subscriptions.meta.total ?? rows.length;
            this.summary.activeSubscribers = rows.filter((row) => activeStatuses.includes(row.status)).length;
            this.summary.expiredSubscribers = rows.filter((row) => ['cancelled', 'expired'].includes(row.status)).length;
            this.summary.totalRevenue = rows
                .filter((row) => row.status === 'active')
                .reduce((sum, row) => sum + Number(row.monthly_recurring_revenue || 0), 0);
        },
    },
});
