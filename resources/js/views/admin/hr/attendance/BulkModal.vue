<template>
    <VModal :show-modal="show" @close-click="close" title="Mark Daily Attendance" size="xl">
        <template #modal-body>

            <!-- ── Top bar: date + quick-mark ────────────────────────────────── -->
            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-4">
                    <VDatepicker id="bulk_date" v-model="form.date" label="Date" required />
                </div>
                <div class="col-md-8">
                    <label class="form-label mb-2">Quick Mark All</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button v-for="s in STATUSES" :key="s.value" type="button"
                            class="btn btn-sm d-flex align-items-center gap-1"
                            :class="s.outlineClass"
                            @click="markAll(s.value)">
                            <i :class="s.icon"></i> {{ s.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Live summary pills ──────────────────────────────────────────── -->
            <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                <span class="fw-semibold text-muted me-1" style="font-size:13px;">Summary:</span>
                <span v-for="s in STATUSES" :key="s.value"
                    v-show="summaryCount(s.value) > 0"
                    class="badge rounded-pill py-1 px-3"
                    :class="s.badgeClass">
                    {{ summaryCount(s.value) }} {{ s.label }}
                </span>
                <span class="ms-auto text-muted" style="font-size:12px;">
                    {{ Object.keys(attendanceMap).length }} employees total
                </span>
            </div>

            <!-- ── Employee search ────────────────────────────────────────────── -->
            <div class="mb-3">
                <div class="search-set w-100">
                    <div class="search-input w-100">
                        <a href="javascript:void(0);" class="btn-searchset">
                            <i class="ti ti-search fs-14"></i>
                        </a>
                        <input v-model="search" type="search" class="form-control"
                            placeholder="Search employee by name or code…" />
                    </div>
                </div>
            </div>

            <!-- ── Employee list ──────────────────────────────────────────────── -->
            <div v-if="employees.loading" class="text-center py-5">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <div class="text-muted">Loading employees…</div>
            </div>

            <template v-else>
                <!-- Column labels -->
                <div class="att-col-labels d-none d-lg-flex mb-1 px-3">
                    <div class="att-emp-col text-muted" style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Employee</div>
                    <div class="att-status-col text-muted" style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Status</div>
                    <div class="att-time-col text-muted" style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Check In / Out</div>
                    <div class="att-note-col text-muted" style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Note</div>
                </div>

                <div class="att-employee-list">
                    <div v-if="filteredEmployees.length === 0" class="text-center py-5 text-muted">
                        <i class="ti ti-search-off d-block mb-2" style="font-size:2rem;"></i>
                        No employees match your search.
                    </div>

                    <div v-for="emp in filteredEmployees" :key="emp.id"
                        class="att-row"
                        :class="rowAccentClass(attendanceMap[emp.id]?.status)">

                        <!-- Employee info -->
                        <div class="att-emp-col">
                            <div class="att-avatar" :class="avatarColorClass(emp.id)">
                                {{ initials(emp.full_name) }}
                            </div>
                            <div class="att-emp-info">
                                <div class="att-emp-name">{{ emp.full_name }}</div>
                                <div class="att-emp-code">{{ emp.employee_code }}</div>
                            </div>
                        </div>

                        <!-- Status pills -->
                        <div class="att-status-col">
                            <div class="att-status-group">
                                <button v-for="s in STATUSES" :key="s.value"
                                    type="button"
                                    class="att-status-pill"
                                    :class="[s.pillClass, { active: (attendanceMap[emp.id]?.status === s.value) }]"
                                    :title="s.label"
                                    @click="setStatus(emp.id, s.value)">
                                    {{ s.short }}
                                </button>
                            </div>
                        </div>

                        <!-- Time fields -->
                        <div class="att-time-col">
                            <div class="att-time-field" :class="{ 'att-time-disabled': isNoTime(attendanceMap[emp.id]?.status) }">
                                <i class="ti ti-login att-time-icon" style="color:#22c55e;"></i>
                                <input type="time"
                                    v-model="attendanceMap[emp.id].check_in"
                                    class="att-time-input"
                                    :disabled="isNoTime(attendanceMap[emp.id]?.status)" />
                            </div>
                            <div class="att-time-separator">→</div>
                            <div class="att-time-field" :class="{ 'att-time-disabled': isNoTime(attendanceMap[emp.id]?.status) }">
                                <i class="ti ti-logout att-time-icon" style="color:#ef4444;"></i>
                                <input type="time"
                                    v-model="attendanceMap[emp.id].check_out"
                                    class="att-time-input"
                                    :disabled="isNoTime(attendanceMap[emp.id]?.status)" />
                            </div>
                            <div v-if="duration(emp.id)" class="att-duration">
                                <i class="ti ti-clock me-1"></i>{{ duration(emp.id) }}
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="att-note-col">
                            <input type="text"
                                v-model="attendanceMap[emp.id].note"
                                class="form-control form-control-sm att-note-input"
                                placeholder="Note…" />
                        </div>
                    </div>
                </div>
            </template>

            <!-- ── Footer ─────────────────────────────────────────────────────── -->
            <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top">
                <p class="text-muted small mb-0">
                    <i class="ti ti-info-circle me-1"></i>
                    Saving will overwrite existing records for this date.
                </p>
                <div class="d-flex gap-2">
                    <button type="button" @click="close" class="btn btn-outline-secondary">Cancel</button>
                    <VButton :loading="isSubmitting" @click="submit" label="Save Attendance" />
                </div>
            </div>

        </template>
    </VModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';
import { useAttendanceStore } from '@/stores/admin/hr/attendance.js';
import VDatepicker from '@/components/base/VDatepicker.vue';

const props = defineProps({ show: Boolean, month: Number, year: Number });
const emit = defineEmits(['update:show', 'saved']);

const empStore = useEmployeeStore();
const attStore = useAttendanceStore();
const { employees } = storeToRefs(empStore);

const form = reactive({ date: new Date().toISOString().slice(0, 10) });
const attendanceMap = reactive({});
const isSubmitting = ref(false);
const search = ref('');

// ── Status definitions ────────────────────────────────────────────────────────

const STATUSES = [
    {
        value:        'present',
        label:        'Present',
        short:        'P',
        icon:         'ti ti-user-check',
        pillClass:    'pill-present',
        outlineClass: 'btn-outline-success',
        badgeClass:   'bg-success',
    },
    {
        value:        'absent',
        label:        'Absent',
        short:        'A',
        icon:         'ti ti-user-x',
        pillClass:    'pill-absent',
        outlineClass: 'btn-outline-danger',
        badgeClass:   'bg-danger',
    },
    {
        value:        'half_day',
        label:        'Half Day',
        short:        'H',
        icon:         'ti ti-clock-half-2',
        pillClass:    'pill-half',
        outlineClass: 'btn-outline-warning',
        badgeClass:   'bg-warning text-dark',
    },
    {
        value:        'late',
        label:        'Late',
        short:        'La',
        icon:         'ti ti-clock-exclamation',
        pillClass:    'pill-late',
        outlineClass: 'btn-outline-secondary',
        badgeClass:   'bg-secondary',
    },
    {
        value:        'on_leave',
        label:        'On Leave',
        short:        'L',
        icon:         'ti ti-beach',
        pillClass:    'pill-leave',
        outlineClass: 'btn-outline-info',
        badgeClass:   'bg-info',
    },
];

// ── Helpers ───────────────────────────────────────────────────────────────────

const resolveStatus = (s) => s?.value ?? s;

const initials = (name) =>
    name.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');

const AVATAR_COLORS = ['avatar-indigo', 'avatar-emerald', 'avatar-rose', 'avatar-amber', 'avatar-sky', 'avatar-violet'];
const avatarColorClass = (id) => AVATAR_COLORS[id % AVATAR_COLORS.length];

const rowAccentClass = (status) => ({
    present:  'accent-present',
    absent:   'accent-absent',
    half_day: 'accent-half',
    late:     'accent-late',
    on_leave: 'accent-leave',
}[resolveStatus(status)] ?? '');

const isNoTime = (status) => ['absent', 'on_leave'].includes(resolveStatus(status));

const duration = (empId) => {
    const entry = attendanceMap[empId];
    if (!entry?.check_in || !entry?.check_out) return null;
    // Slice to "HH:MM" in case the API returns "HH:MM:SS"
    const [ih, im] = entry.check_in.slice(0, 5).split(':').map(Number);
    const [oh, om] = entry.check_out.slice(0, 5).split(':').map(Number);
    const diff = (oh * 60 + om) - (ih * 60 + im);
    if (diff <= 0) return null;
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    return h > 0 ? (m > 0 ? `${h}h ${m}m` : `${h}h`) : `${m}m`;
};

// ── Computed ──────────────────────────────────────────────────────────────────

const filteredEmployees = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return employees.value.data;
    return employees.value.data.filter(emp =>
        emp.full_name.toLowerCase().includes(q) ||
        emp.employee_code?.toLowerCase().includes(q)
    );
});

const summaryCount = (value) =>
    Object.values(attendanceMap).filter(a => resolveStatus(a.status) === value).length;

// ── Actions ───────────────────────────────────────────────────────────────────

const setStatus = (empId, value) => {
    if (attendanceMap[empId]) {
        attendanceMap[empId].status = value;
        if (isNoTime({ value })) {
            attendanceMap[empId].check_in = '';
            attendanceMap[empId].check_out = '';
        }
    }
};

const markAll = (value) => {
    employees.value.data.forEach(emp => setStatus(emp.id, value));
};

const close = () => {
    search.value = '';
    emit('update:show', false);
};

// ── Data loading ──────────────────────────────────────────────────────────────

watch(() => props.show, (val) => {
    if (!val) return;
    empStore.getEmployees({ limit: 200, status: 'active' }).then(() => {
        employees.value.data.forEach(emp => {
            if (!attendanceMap[emp.id]) {
                attendanceMap[emp.id] = {
                    employee_id: emp.id,
                    status:      'present',
                    check_in:    '',
                    check_out:   '',
                    note:        '',
                };
            }
        });
    });
});

// ── Submit ────────────────────────────────────────────────────────────────────

const submit = async () => {
    isSubmitting.value = true;
    try {
        const res = await attStore.bulkStore({
            date:        form.date,
            attendances: Object.values(attendanceMap),
        });
        toast(res.status, res.data.message);
        emit('saved');
        close();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<style scoped>
/* ── Employee list container ─────────────────────────────────── */
.att-employee-list {
    max-height: 440px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}

/* ── Column label row ────────────────────────────────────────── */
.att-col-labels {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 12px;
}

/* ── Each employee row ───────────────────────────────────────── */
.att-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
    border-left: 4px solid transparent;
}

.att-row:last-child { border-bottom: none; }
.att-row:hover { background: #f8fafc; }

/* Status accent borders */
.accent-present  { border-left-color: #22c55e; }
.accent-absent   { border-left-color: #ef4444; }
.accent-half     { border-left-color: #f59e0b; }
.accent-late     { border-left-color: #94a3b8; }
.accent-leave    { border-left-color: #38bdf8; }

/* ── Column widths ───────────────────────────────────────────── */
.att-emp-col    { display: flex; align-items: center; gap: 10px; flex: 0 0 220px; min-width: 0; }
.att-status-col { flex: 0 0 auto; }
.att-time-col   { display: flex; align-items: center; gap: 6px; flex: 1 1 auto; min-width: 0; }
.att-note-col   { flex: 0 0 160px; }

/* ── Avatar ──────────────────────────────────────────────────── */
.att-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    letter-spacing: .3px;
}

.avatar-indigo  { background: #e0e7ff; color: #4338ca; }
.avatar-emerald { background: #d1fae5; color: #065f46; }
.avatar-rose    { background: #ffe4e6; color: #9f1239; }
.avatar-amber   { background: #fef9c3; color: #854d0e; }
.avatar-sky     { background: #e0f2fe; color: #0369a1; }
.avatar-violet  { background: #ede9fe; color: #5b21b6; }

/* ── Employee name / code ────────────────────────────────────── */
.att-emp-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.att-emp-code { font-size: 11px; color: #94a3b8; }

/* ── Status pill group ───────────────────────────────────────── */
.att-status-group {
    display: flex;
    gap: 4px;
}

.att-status-pill {
    width: 32px;
    height: 26px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    background: #f1f5f9;
    color: #94a3b8;
}

.att-status-pill:hover { transform: translateY(-1px); }

/* Pill active states */
.pill-present.active   { background: #22c55e; border-color: #16a34a; color: #fff; }
.pill-present:hover    { border-color: #22c55e; color: #22c55e; background: #f0fdf4; }
.pill-absent.active    { background: #ef4444; border-color: #dc2626; color: #fff; }
.pill-absent:hover     { border-color: #ef4444; color: #ef4444; background: #fef2f2; }
.pill-half.active      { background: #f59e0b; border-color: #d97706; color: #fff; }
.pill-half:hover       { border-color: #f59e0b; color: #f59e0b; background: #fffbeb; }
.pill-late.active      { background: #94a3b8; border-color: #64748b; color: #fff; }
.pill-late:hover       { border-color: #94a3b8; color: #64748b; background: #f8fafc; }
.pill-leave.active     { background: #38bdf8; border-color: #0284c7; color: #fff; }
.pill-leave:hover      { border-color: #38bdf8; color: #0284c7; background: #f0f9ff; }

/* ── Time fields ─────────────────────────────────────────────── */
.att-time-field {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 3px 7px;
    transition: border-color .15s;
}

.att-time-field:focus-within { border-color: #818cf8; background: #fff; }
.att-time-disabled { opacity: .4; pointer-events: none; }

.att-time-icon { font-size: 13px; flex-shrink: 0; }

.att-time-input {
    border: none;
    background: transparent;
    font-size: 12px;
    width: 76px;
    outline: none;
    color: #1e293b;
    padding: 0;
}

.att-time-separator { color: #cbd5e1; font-size: 12px; flex-shrink: 0; }

.att-duration {
    font-size: 11px;
    font-weight: 600;
    color: #6366f1;
    background: #eef2ff;
    border-radius: 4px;
    padding: 2px 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

/* ── Note input ──────────────────────────────────────────────── */
.att-note-input {
    font-size: 12px;
    background: #f8fafc;
    border-color: #e2e8f0;
}
.att-note-input:focus { background: #fff; border-color: #818cf8; }
.att-note-input::placeholder { color: #cbd5e1; }

/* ── Scrollbar styling ───────────────────────────────────────── */
.att-employee-list::-webkit-scrollbar { width: 5px; }
.att-employee-list::-webkit-scrollbar-track { background: #f1f5f9; }
.att-employee-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
