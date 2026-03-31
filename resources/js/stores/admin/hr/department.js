import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'hr/department';

export const useDepartmentStore = defineStore('department', {
    state: () => ({
        departments: { data: [], loading: false, meta: {} },
        department: { data: {}, loading: false },
    }),

    actions: {
        getDepartments(params = {}) {
            this.departments.loading = true;
            return apiAdmin(apiUrl, 'get', params)
                .then((res) => {
                    this.departments.data = res.data.data;
                    this.departments.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.departments.loading = false; });
        },
        storeDepartment(form) {
            return apiAdmin(apiUrl, 'post', form)
                .then((res) => {
                    this.departments.data.unshift(res.data.data);
                    return res;
                });
        },
        getDepartment(id) {
            this.department.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => { this.department.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.department.loading = false; });
        },
        updateDepartment(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const idx = this.departments.data.findIndex(d => d.id === id);
                    if (idx !== -1) this.departments.data[idx] = res.data.data;
                    return res;
                });
        },
        deleteDepartment(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.departments.data = this.departments.data.filter(d => d.id !== id);
                    return res;
                });
        },
    },
});
