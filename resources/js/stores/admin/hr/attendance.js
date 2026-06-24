import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export const useAttendanceStore = defineStore('attendance', {
    state: () => ({
        attendances: { data: [], loading: false, meta: {} },
        monthlySheet: { data: [], loading: false },
        workSchedule: { data: {}, loading: false },
    }),

    actions: {
        getWorkSchedule() {
            this.workSchedule.loading = true;
            return apiAdmin('hr/work-schedule')
                .then((res) => { this.workSchedule.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.workSchedule.loading = false; });
        },
        updateWorkSchedule(form) {
            return apiAdmin('hr/work-schedule', 'put', form).then((res) => {
                this.workSchedule.data = res.data.data;
                return res;
            });
        },
        getAttendances(params = {}) {
            this.attendances.loading = true;
            return apiAdmin('hr/attendance', 'get', params)
                .then((res) => {
                    this.attendances.data = res.data.data;
                    this.attendances.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => { this.attendances.loading = false; });
        },
        getMonthlySheet(params = {}) {
            this.monthlySheet.loading = true;
            return apiAdmin('hr/attendance/monthly', 'get', params)
                .then((res) => { this.monthlySheet.data = res.data.data; })
                .catch(showErrors)
                .finally(() => { this.monthlySheet.loading = false; });
        },
        bulkStore(form) {
            return apiAdmin('hr/attendance/bulk', 'post', form);
        },
        storeAttendance(form) {
            return apiAdmin('hr/attendance', 'post', form).then((res) => {
                this.attendances.data.unshift(res.data.data);
                return res;
            });
        },
        updateAttendance(id, form) {
            return apiAdmin(`hr/attendance/${id}`, 'put', form).then((res) => {
                const idx = this.attendances.data.findIndex(d => d.id === id);
                if (idx !== -1) this.attendances.data[idx] = res.data.data;
                return res;
            });
        },
        deleteAttendance(id) {
            return apiAdmin(`hr/attendance/${id}`, 'delete').then((res) => {
                this.attendances.data = this.attendances.data.filter(d => d.id !== id);
                return res;
            });
        },
    },
});
