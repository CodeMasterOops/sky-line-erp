<template>
    <VModal :show-modal="show" @close-click="$emit('update:show', false)" title="Mark Daily Attendance" size="xl">
        <template #modal-body>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <VInput id="date" v-model="form.date" label="Date *" type="date" />
                </div>
            </div>

            <VLoader v-if="employees.loading" :colspan="4" />
            <div v-else class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="emp in employees.data" :key="emp.id">
                            <td>{{ emp.full_name }} <small class="text-muted">({{ emp.employee_code }})</small></td>
                            <td>
                                <select v-model="attendanceMap[emp.id].status" class="form-select form-select-sm">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="half_day">Half Day</option>
                                    <option value="late">Late</option>
                                    <option value="on_leave">On Leave</option>
                                </select>
                            </td>
                            <td><input type="time" v-model="attendanceMap[emp.id].check_in" class="form-control form-control-sm" /></td>
                            <td><input type="time" v-model="attendanceMap[emp.id].check_out" class="form-control form-control-sm" /></td>
                            <td><input type="text" v-model="attendanceMap[emp.id].note" class="form-control form-control-sm" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="text-end mt-3">
                <button type="button" @click="$emit('update:show', false)" class="btn btn-danger me-2">Close</button>
                <VButton :loading="isSubmitting" @click="submit" label="Save Attendance" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';
import { useAttendanceStore } from '@/stores/admin/hr/attendance.js';

const props = defineProps({ show: Boolean, month: Number, year: Number });
const emit = defineEmits(['update:show', 'saved']);

const empStore = useEmployeeStore();
const attStore = useAttendanceStore();
const { employees } = storeToRefs(empStore);

const form = reactive({ date: new Date().toISOString().slice(0, 10) });
const attendanceMap = reactive({});
const isSubmitting = ref(false);

watch(() => props.show, (val) => {
    if (val) {
        empStore.getEmployees({ limit: 200 }).then(() => {
            employees.value.data.forEach(emp => {
                if (!attendanceMap[emp.id]) {
                    attendanceMap[emp.id] = { employee_id: emp.id, status: 'present', check_in: '', check_out: '', note: '' };
                }
            });
        });
    }
});

const submit = async () => {
    isSubmitting.value = true;
    try {
        const res = await attStore.bulkStore({
            date: form.date,
            attendances: Object.values(attendanceMap),
        });
        toast(res.status, res.data.message);
        emit('saved');
        emit('update:show', false);
    } catch (e) { showErrors(e); }
    finally { isSubmitting.value = false; }
};
</script>
