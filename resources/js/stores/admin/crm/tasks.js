import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'crm/task';

export const useCrmTaskStore = defineStore('crmTask', {
    state: () => ({
        tasks: {
            data: [],
            meta: {},
            loading: false,
        },
    }),

    actions: {
        getTasks({ filter, mine = false } = {}) {
            this.tasks.loading = true;
            const url = mine ? `${apiUrl}/mine` : apiUrl;
            const q = new URLSearchParams({ ...filter }).toString();
            return apiAdmin(`${url}?${q}`)
                .then((res) => {
                    this.tasks.data = res.data.data;
                    this.tasks.meta = res.data.meta ?? {};
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.tasks.loading = false;
                });
        },
        storeTask(form) {
            return apiAdmin(apiUrl, 'post', form);
        },
        updateTask(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form);
        },
        completeTask(id) {
            return apiAdmin(`${apiUrl}/${id}/complete`, 'post');
        },
        deleteTask(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete');
        },
    },
});
