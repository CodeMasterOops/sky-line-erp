<template>
    <PageHeader title="Work Schedule" subtitle="Standard working hours, grace period and overtime policy" />

    <section class="section">
        <VLoader v-if="workSchedule.loading" :colspan="2" />
        <form v-else @submit.prevent="save" class="card">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Schedule Name</label>
                    <input v-model="form.name" type="text" class="form-control" />
                </div>

                <div class="col-md-3">
                    <label class="form-label">Start Time</label>
                    <input v-model="form.start_time" type="time" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Time</label>
                    <input v-model="form.end_time" type="time" class="form-control" />
                </div>

                <div class="col-md-3">
                    <label class="form-label">Grace (minutes)</label>
                    <input v-model.number="form.grace_minutes" type="number" min="0" class="form-control" />
                    <small class="text-muted">Late only after this buffer.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standard Hours / Day</label>
                    <input v-model.number="form.standard_hours_per_day" type="number" step="0.5" min="1" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Overtime Multiplier</label>
                    <input v-model.number="form.overtime_multiplier" type="number" step="0.1" min="1" class="form-control" />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="otEnabled" v-model="form.overtime_enabled" class="form-check-input" type="checkbox" />
                        <label for="otEnabled" class="form-check-label">Pay overtime in payroll</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Weekly Off Days</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div v-for="day in weekDays" :key="day.value" class="form-check">
                            <input :id="`off-${day.value}`" v-model="form.weekly_off_days" :value="day.value" class="form-check-input" type="checkbox" />
                            <label :for="`off-${day.value}`" class="form-check-label">{{ day.label }}</label>
                        </div>
                    </div>
                    <small class="text-muted">In Nepal the weekly off is typically Saturday only.</small>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" :disabled="saving" class="btn btn-primary">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save Schedule
                    </button>
                </div>
            </div>
        </form>
    </section>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useAttendanceStore } from '@/stores/admin/hr/attendance.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const attendanceStore = useAttendanceStore();
const { workSchedule } = storeToRefs(attendanceStore);

const saving = ref(false);

const weekDays = [
    { value: 0, label: 'Sunday' }, { value: 1, label: 'Monday' }, { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' }, { value: 4, label: 'Thursday' }, { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

const form = reactive({
    name: 'Default', start_time: '10:00', end_time: '17:00', grace_minutes: 0,
    standard_hours_per_day: 8, overtime_multiplier: 1.5, overtime_enabled: false,
    weekly_off_days: [6],
});

onMounted(async () => {
    await attendanceStore.getWorkSchedule();
    Object.assign(form, {
        name: workSchedule.value.data?.name ?? 'Default',
        start_time: workSchedule.value.data?.start_time ?? '10:00',
        end_time: workSchedule.value.data?.end_time ?? '17:00',
        grace_minutes: workSchedule.value.data?.grace_minutes ?? 0,
        standard_hours_per_day: workSchedule.value.data?.standard_hours_per_day ?? 8,
        overtime_multiplier: workSchedule.value.data?.overtime_multiplier ?? 1.5,
        overtime_enabled: !!workSchedule.value.data?.overtime_enabled,
        weekly_off_days: workSchedule.value.data?.weekly_off_days ?? [6],
    });
});

const save = async () => {
    saving.value = true;
    try {
        const res = await attendanceStore.updateWorkSchedule(form);
        toast(res.status, res.data.message);
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};
</script>
