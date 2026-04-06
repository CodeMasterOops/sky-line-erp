import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'hr/employee';

export const useEmployeeStore = defineStore('employee', {
    state: () => ({
        employees: { data: [], loading: false, meta: {} },
        employee: { data: {}, loading: false },
    }),

    actions: {
        getEmployees(params = {}) {
            this.employees.loading = true;
            return apiAdmin(apiUrl, 'get', params)
                .then((res) => {
                    this.employees.data = res.data.data;
                    this.employees.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.employees.loading = false; });
        },
        storeEmployee(form) {
            return apiAdmin(apiUrl, 'post', form)
                .then((res) => {
                    this.employees.data.unshift(res.data.data);
                    return res;
                });
        },
        getEmployee(id) {
            this.employee.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => { this.employee.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.employee.loading = false; });
        },
        updateEmployee(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const idx = this.employees.data.findIndex(d => d.id === id);
                    if (idx !== -1) this.employees.data[idx] = res.data.data;
                    return res;
                });
        },
        deleteEmployee(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.employees.data = this.employees.data.filter(d => d.id !== id);
                    return res;
                });
        },
    },
});
