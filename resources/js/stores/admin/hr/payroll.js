import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export const usePayrollStore = defineStore('payroll', {
    state: () => ({
        components: { data: [], loading: false },
        structures: { data: [], loading: false, meta: {} },
        runs: { data: [], loading: false, meta: {} },
        run: { data: {}, loading: false },
        payslips: { data: [], loading: false, meta: {} },
        payslip: { data: {}, loading: false },
    }),

    actions: {
        // Salary Components
        getComponents() {
            this.components.loading = true;
            return apiAdmin('hr/salary-component')
                .then((res) => { this.components.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.components.loading = false; });
        },
        storeComponent(form) {
            return apiAdmin('hr/salary-component', 'post', form).then((res) => {
                this.components.data.push(res.data.data);
                return res;
            });
        },
        updateComponent(id, form) {
            return apiAdmin(`hr/salary-component/${id}`, 'put', form).then((res) => {
                const idx = this.components.data.findIndex(d => d.id === id);
                if (idx !== -1) this.components.data[idx] = res.data.data;
                return res;
            });
        },
        deleteComponent(id) {
            return apiAdmin(`hr/salary-component/${id}`, 'delete').then((res) => {
                this.components.data = this.components.data.filter(d => d.id !== id);
                return res;
            });
        },

        // Salary Structures
        getStructures(params = {}) {
            this.structures.loading = true;
            return apiAdmin('hr/salary-structure', 'get', params)
                .then((res) => {
                    this.structures.data = res.data.data;
                    this.structures.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.structures.loading = false; });
        },
        storeStructure(form) {
            return apiAdmin('hr/salary-structure', 'post', form).then((res) => {
                this.structures.data.unshift(res.data.data);
                return res;
            });
        },
        updateStructure(id, form) {
            return apiAdmin(`hr/salary-structure/${id}`, 'put', form).then((res) => {
                const idx = this.structures.data.findIndex(d => d.id === id);
                if (idx !== -1) this.structures.data[idx] = res.data.data;
                return res;
            });
        },
        deleteStructure(id) {
            return apiAdmin(`hr/salary-structure/${id}`, 'delete').then((res) => {
                this.structures.data = this.structures.data.filter(d => d.id !== id);
                return res;
            });
        },

        // Payroll Runs
        getRuns(params = {}) {
            this.runs.loading = true;
            return apiAdmin('hr/payroll', 'get', params)
                .then((res) => {
                    this.runs.data = res.data.data;
                    this.runs.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.runs.loading = false; });
        },
        storeRun(form) {
            return apiAdmin('hr/payroll', 'post', form).then((res) => {
                this.runs.data.unshift(res.data.data);
                return res;
            });
        },
        getRun(id) {
            this.run.loading = true;
            return apiAdmin(`hr/payroll/${id}`)
                .then((res) => { this.run.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.run.loading = false; });
        },
        processRun(id) {
            return apiAdmin(`hr/payroll/${id}/process`, 'post').then((res) => {
                this.run.data = res.data.data;
                const idx = this.runs.data.findIndex(d => d.id === id);
                if (idx !== -1) this.runs.data[idx] = res.data.data;
                return res;
            });
        },
        confirmRun(id, payload = {}) {
            return apiAdmin(`hr/payroll/${id}/confirm`, 'post', payload).then((res) => {
                this.run.data = res.data.data;
                const idx = this.runs.data.findIndex(d => d.id === id);
                if (idx !== -1) this.runs.data[idx] = res.data.data;
                return res;
            });
        },
        deleteRun(id) {
            return apiAdmin(`hr/payroll/${id}`, 'delete').then((res) => {
                this.runs.data = this.runs.data.filter(d => d.id !== id);
                return res;
            });
        },

        // Payslips
        getPayslips(params = {}) {
            this.payslips.loading = true;
            return apiAdmin('hr/payslip', 'get', params)
                .then((res) => {
                    this.payslips.data = res.data.data;
                    this.payslips.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.payslips.loading = false; });
        },
        getPayslip(id) {
            this.payslip.loading = true;
            return apiAdmin(`hr/payslip/${id}`)
                .then((res) => { this.payslip.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.payslip.loading = false; });
        },
    },
});
