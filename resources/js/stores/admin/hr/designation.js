import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'hr/designation';

export const useDesignationStore = defineStore('designation', {
    state: () => ({
        designations: { data: [], loading: false, meta: {} },
        designation: { data: {}, loading: false },
    }),

    actions: {
        getDesignations(params = {}) {
            this.designations.loading = true;
            return apiAdmin(apiUrl, 'get', params)
                .then((res) => {
                    this.designations.data = res.data.data;
                    this.designations.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.designations.loading = false; });
        },
        storeDesignation(form) {
            return apiAdmin(apiUrl, 'post', form)
                .then((res) => {
                    this.designations.data.unshift(res.data.data);
                    return res;
                });
        },
        getDesignation(id) {
            this.designation.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => { this.designation.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.designation.loading = false; });
        },
        updateDesignation(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const idx = this.designations.data.findIndex(d => d.id === id);
                    if (idx !== -1) this.designations.data[idx] = res.data.data;
                    return res;
                });
        },
        deleteDesignation(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.designations.data = this.designations.data.filter(d => d.id !== id);
                    return res;
                });
        },
    },
});
