import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export const useLeaveStore = defineStore('leave', {
    state: () => ({
        leaveTypes: { data: [], loading: false },
        applications: { data: [], loading: false, meta: {} },
        holidays: { data: [], loading: false },
    }),

    actions: {
        getLeaveTypes() {
            this.leaveTypes.loading = true;
            return apiAdmin('hr/leave-type')
                .then((res) => { this.leaveTypes.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.leaveTypes.loading = false; });
        },
        storeLeaveType(form) {
            return apiAdmin('hr/leave-type', 'post', form).then((res) => {
                this.leaveTypes.data.push(res.data.data);
                return res;
            });
        },
        updateLeaveType(id, form) {
            return apiAdmin(`hr/leave-type/${id}`, 'put', form).then((res) => {
                const idx = this.leaveTypes.data.findIndex(d => d.id === id);
                if (idx !== -1) this.leaveTypes.data[idx] = res.data.data;
                return res;
            });
        },
        deleteLeaveType(id) {
            return apiAdmin(`hr/leave-type/${id}`, 'delete').then((res) => {
                this.leaveTypes.data = this.leaveTypes.data.filter(d => d.id !== id);
                return res;
            });
        },

        getApplications(params = {}) {
            this.applications.loading = true;
            return apiAdmin('hr/leave-application', 'get', params)
                .then((res) => {
                    this.applications.data = res.data.data;
                    this.applications.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.applications.loading = false; });
        },
        storeApplication(form) {
            return apiAdmin('hr/leave-application', 'post', form).then((res) => {
                this.applications.data.unshift(res.data.data);
                return res;
            });
        },
        approveApplication(id) {
            return apiAdmin(`hr/leave-application/${id}/approve`, 'post').then((res) => {
                const idx = this.applications.data.findIndex(d => d.id === id);
                if (idx !== -1) this.applications.data[idx] = res.data.data;
                return res;
            });
        },
        rejectApplication(id, reason) {
            return apiAdmin(`hr/leave-application/${id}/reject`, 'post', { rejection_reason: reason }).then((res) => {
                const idx = this.applications.data.findIndex(d => d.id === id);
                if (idx !== -1) this.applications.data[idx] = res.data.data;
                return res;
            });
        },
        deleteApplication(id) {
            return apiAdmin(`hr/leave-application/${id}`, 'delete').then((res) => {
                this.applications.data = this.applications.data.filter(d => d.id !== id);
                return res;
            });
        },

        getHolidays(params = {}) {
            this.holidays.loading = true;
            return apiAdmin('hr/holiday', 'get', params)
                .then((res) => { this.holidays.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.holidays.loading = false; });
        },
        storeHoliday(form) {
            return apiAdmin('hr/holiday', 'post', form).then((res) => {
                this.holidays.data.push(res.data.data);
                return res;
            });
        },
        updateHoliday(id, form) {
            return apiAdmin(`hr/holiday/${id}`, 'put', form).then((res) => {
                const idx = this.holidays.data.findIndex(d => d.id === id);
                if (idx !== -1) this.holidays.data[idx] = res.data.data;
                return res;
            });
        },
        deleteHoliday(id) {
            return apiAdmin(`hr/holiday/${id}`, 'delete').then((res) => {
                this.holidays.data = this.holidays.data.filter(d => d.id !== id);
                return res;
            });
        },
    },
});
