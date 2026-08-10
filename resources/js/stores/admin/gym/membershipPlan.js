import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'gym/membership-plan';

export const useMembershipPlanStore = defineStore('gymMembershipPlan', {
    state: () => ({
        plans: {
            data: [],
            meta: {},
            loading: false,
        },
    }),

    getters: {
        activePlans: (state) => state.plans.data.filter((plan) => plan.is_active),
    },

    actions: {
        getPlans({ filter = {} } = {}) {
            this.plans.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiAdmin(`${apiUrl}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.plans.data = res.data.data;
                    this.plans.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.plans.loading = false;
                });
        },

        storePlan(form) {
            return apiAdmin(apiUrl, 'post', form);
        },

        updatePlan(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form);
        },

        toggleActive(id) {
            return apiAdmin(`${apiUrl}/${id}/toggle-active`, 'put');
        },

        deletePlan(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete').then((res) => {
                this.plans.data = this.plans.data.filter((p) => p.id !== id);
                return res;
            });
        },
    },
});
