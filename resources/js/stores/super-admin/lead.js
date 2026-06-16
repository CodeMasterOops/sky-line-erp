import {defineStore} from 'pinia';
import {apiSuperAdmin} from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

const apiUrl = 'lead';

export const useLeadStore = defineStore('lead', {
    state: () => ({
        leads: {
            data: [],
            meta: {},
            loading: false,
        },
        lead: {
            data: {},
            loading: false,
        },
        stats: {
            total: 0,
            new: 0,
            contacted: 0,
            demoGiven: 0,
            converted: 0,
        },
    }),

    actions: {
        getLeads({filter = {}} = {}) {
            this.leads.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiSuperAdmin(`${apiUrl}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.leads.data = res.data.data;
                    this.leads.meta = res.data.meta || {};
                    this.updateStats();
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.leads.loading = false;
                });
        },

        getLead(id) {
            this.lead.loading = true;
            return apiSuperAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.lead.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.lead.loading = false;
                });
        },

        updateLead(id, form) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.leads.data.findIndex((d) => d.id === id);
                    if (index !== -1) {
                        this.leads.data[index] = res.data.data;
                    }
                    this.lead.data = res.data.data;
                    this.updateStats();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },

        deleteLead(id) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.leads.data = this.leads.data.filter((d) => d.id !== id);
                    this.updateStats();
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },

        updateStats() {
            const data = this.leads.data;
            this.stats.total = this.leads.meta.total ?? data.length;
            this.stats.new = data.filter((l) => l.status === 'new').length;
            this.stats.contacted = data.filter((l) => l.status === 'contacted').length;
            this.stats.demoGiven = data.filter((l) => l.status === 'demo_given').length;
            this.stats.converted = data.filter((l) => l.status === 'converted').length;
        },
    },
});
