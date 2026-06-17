<template>
    <PageHeader title="Attendance" subtitle="Track employee attendance" @refresh="loadSheet">
        <template #actions>
            <button type="button" @click="showBulkModal = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-calendar-plus me-2"></i> Mark Attendance
            </button>
        </template>
    </PageHeader>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4 col-6">
            <div class="card mb-0 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10"
                        style="width:44px;height:44px;flex-shrink:0;">
                        <i class="ti ti-user-check text-success fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 lh-1">{{ summary.present }}</div>
                        <div class="text-muted small">Present</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-6">
            <div class="card mb-0 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10"
                        style="width:44px;height:44px;flex-shrink:0;">
                        <i class="ti ti-user-x text-danger fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 lh-1">{{ summary.absent }}</div>
                        <div class="text-muted small">Absent</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-6">
            <div class="card mb-0 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10"
                        style="width:44px;height:44px;flex-shrink:0;">
                        <i class="ti ti-clock-half-2 text-warning fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 lh-1">{{ summary.half_day }}</div>
                        <div class="text-muted small">Half Day</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-6">
            <div class="card mb-0 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary bg-opacity-10"
                        style="width:44px;height:44px;flex-shrink:0;">
                        <i class="ti ti-clock-exclamation text-secondary fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 lh-1">{{ summary.late }}</div>
                        <div class="text-muted small">Late</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-6">
            <div class="card mb-0 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-10"
                        style="width:44px;height:44px;flex-shrink:0;">
                        <i class="ti ti-beach text-info fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 lh-1">{{ summary.on_leave }}</div>
                        <div class="text-muted small">On Leave</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-list-card">
        <!-- Toolbar -->
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <a href="javascript:void(0);" class="btn-searchset">
                        <i class="ti ti-search fs-14 feather-search"></i>
                    </a>
                    <input v-model="employeeSearch" type="search" class="form-control form-control-sm"
                        placeholder="Search employee..." />
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small me-1">
                    <i class="ti ti-calendar-stats me-1"></i>{{ currentPeriodLabel }}
                </span>
                <!-- Month Dropdown -->
                <div class="dropdown">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        <i class="ti ti-calendar me-1"></i> {{ monthName(filter.month) }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3" style="min-width:160px;">
                        <li v-for="m in 12" :key="m">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                :class="{ 'active': filter.month === m }"
                                @click="filter.month = m">{{ monthName(m) }}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div v-if="monthlySheet.loading" class="d-flex align-items-center justify-content-center py-5">
                <div class="spinner-border text-primary me-2" role="status"></div>
                <span class="text-muted">Loading attendance sheet…</span>
            </div>

            <div v-else-if="filteredSheet.length === 0" class="text-center py-5 text-muted">
                <i class="ti ti-mood-empty fs-1 mb-2 d-block"></i>
                No attendance records found.
            </div>

            <div v-else class="table-responsive">
                <table class="table table-bordered table-sm mb-0 attendance-grid">
                    <thead>
                        <tr>
                            <th class="sticky-col bg-white fw-semibold" style="min-width:180px;">
                                Employee
                                <span class="badge bg-secondary ms-1">{{ filteredSheet.length }}</span>
                            </th>
                            <th v-for="day in daysInMonth" :key="day"
                                class="text-center fw-semibold"
                                :class="{ 'weekend-col': isWeekend(day) }"
                                style="min-width:58px; width:58px;">
                                <div>{{ parseInt(day) }}</div>
                                <div class="text-muted" style="font-size:10px; font-weight:400;">{{ dayLabel(day) }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filteredSheet" :key="row.employee.id">
                            <td class="sticky-col bg-white">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="employee-avatar">
                                        {{ avatarInitials(row.employee.full_name) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold lh-sm" style="font-size:13px;">{{ row.employee.full_name }}</div>
                                        <div class="text-muted" style="font-size:11px;">{{ row.employee.employee_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td v-for="day in daysInMonth" :key="day"
                                class="text-center p-0"
                                :class="{ 'weekend-col': isWeekend(day) }">
                                <template v-if="row.attendances[day]">
                                    <div :class="cellClass(row.attendances[day].status)"
                                        :title="cellTooltip(row.attendances[day])"
                                        class="attendance-cell">
                                        <span class="cell-label">{{ cellLabel(row.attendances[day].status) }}</span>
                                        <span v-if="row.attendances[day].check_in" class="cell-time cell-in">
                                            {{ formatTime(row.attendances[day].check_in) }}
                                        </span>
                                        <span v-if="row.attendances[day].check_out" class="cell-time cell-out">
                                            {{ formatTime(row.attendances[day].check_out) }}
                                        </span>
                                    </div>
                                </template>
                                <div v-else class="attendance-cell attendance-empty" title="Not marked">
                                    <span class="cell-label text-muted">—</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Legend -->
            <div v-if="!monthlySheet.loading && filteredSheet.length > 0"
                class="d-flex gap-3 flex-wrap align-items-center px-3 py-2 border-top bg-light" style="font-size:12px;">
                <span class="d-flex align-items-center gap-1">
                    <span class="legend-dot bg-success"></span> P — Present
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="legend-dot bg-danger"></span> A — Absent
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="legend-dot bg-warning"></span> H — Half Day
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="legend-dot bg-secondary"></span> La — Late
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="legend-dot bg-info"></span> L — On Leave
                </span>
                <span class="d-flex align-items-center gap-1 ms-auto text-muted">
                    <i class="ti ti-login me-1"></i> Top = Check-in &nbsp;
                    <i class="ti ti-logout me-1"></i> Bottom = Check-out
                </span>
            </div>
        </div>
    </div>

    <BulkAttendanceModal v-model:show="showBulkModal" :month="filter.month" :year="currentYear" @saved="loadSheet" />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useAttendanceStore } from '@/stores/admin/hr/attendance.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { apiAdmin } from '@/helpers/api.js';
import BulkAttendanceModal from './BulkModal.vue';

const store = useAttendanceStore();
const { monthlySheet } = storeToRefs(store);
const showBulkModal = ref(false);
const employeeSearch = ref('');

const now = new Date();
const fiscalYear = ref(null);

// Derive the calendar year from the current fiscal year so month selection is accurate.
// Falls back to current calendar year if fiscal year data isn't available.
const currentYear = computed(() => {
    if (!fiscalYear.value) return now.getFullYear();
    return new Date(fiscalYear.value.start_date).getFullYear();
});

const currentPeriodLabel = computed(() => `${monthName(filter.month)} ${currentYear.value}`);

const loadSheet = () => store.getMonthlySheet({ month: filter.month, year: currentYear.value });

const { filter } = useUrlFilter({
    defaults: { month: now.getMonth() + 1 },
    onFilter: loadSheet,
});

onMounted(async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year');
        fiscalYear.value = res.data.data?.find(fy => fy.is_current) ?? null;
    } catch { /* fall back to calendar year */ }
});

// ── Helpers ──────────────────────────────────────────────────────────────────

const monthName = (m) => new Date(2000, m - 1, 1).toLocaleString('default', { month: 'long' });

const daysInMonth = computed(() => {
    const count = new Date(currentYear.value, filter.month, 0).getDate();
    return Array.from({ length: count }, (_, i) => String(i + 1).padStart(2, '0'));
});

const isWeekend = (day) => {
    const d = new Date(currentYear.value, filter.month - 1, parseInt(day)).getDay();
    return d === 0 || d === 6;
};

const dayLabel = (day) => new Date(currentYear.value, filter.month - 1, parseInt(day))
    .toLocaleString('default', { weekday: 'short' }).slice(0, 2);

const avatarInitials = (name) => name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();

const STATUS_MAP = {
    present:  { label: 'P',  cls: 'cell-present' },
    absent:   { label: 'A',  cls: 'cell-absent' },
    half_day: { label: 'H',  cls: 'cell-half' },
    late:     { label: 'La', cls: 'cell-late' },
    on_leave: { label: 'L',  cls: 'cell-leave' },
};

const resolveStatus = (status) => status?.value ?? status;

const cellLabel = (status) => STATUS_MAP[resolveStatus(status)]?.label ?? '?';
const cellClass = (status) => STATUS_MAP[resolveStatus(status)]?.cls ?? '';

// Strip seconds: "10:00:00" → "10:00", "10:00" → "10:00"
const formatTime = (t) => t ? t.slice(0, 5) : '';

const cellTooltip = (att) => {
    const parts = [att.status_label || resolveStatus(att.status)];
    if (att.check_in)  parts.push(`In: ${formatTime(att.check_in)}`);
    if (att.check_out) parts.push(`Out: ${formatTime(att.check_out)}`);
    if (att.note)      parts.push(`Note: ${att.note}`);
    return parts.join(' · ');
};

// ── Filtered sheet (client-side employee search) ──────────────────────────────

const filteredSheet = computed(() => {
    const q = employeeSearch.value.toLowerCase();
    if (!q) return monthlySheet.value.data;
    return monthlySheet.value.data.filter(row =>
        row.employee.full_name.toLowerCase().includes(q) ||
        row.employee.employee_code?.toLowerCase().includes(q)
    );
});

// ── Summary counts ────────────────────────────────────────────────────────────

const summary = computed(() => {
    const s = { present: 0, absent: 0, half_day: 0, late: 0, on_leave: 0 };
    monthlySheet.value.data.forEach(row => {
        Object.values(row.attendances).forEach(att => {
            const k = resolveStatus(att.status);
            if (k in s) s[k]++;
        });
    });
    return s;
});
</script>

<style scoped>
/* Sticky employee column */
.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
    border-right: 2px solid #dee2e6 !important;
}

.attendance-grid thead .sticky-col {
    z-index: 3;
}

/* Weekend columns */
.weekend-col {
    background-color: #f8f9fa;
}

/* Attendance cells */
.attendance-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4px 2px;
    min-height: 52px;
    cursor: default;
    gap: 1px;
}

.cell-label {
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}

.cell-time {
    font-size: 9px;
    font-weight: 600;
    line-height: 1.2;
    border-radius: 2px;
    padding: 0 3px;
}

.cell-in  { color: #065f46; background: rgba(34,197,94,.15); }
.cell-out { color: #991b1b; background: rgba(239,68,68,.15); }

/* Override colors inside colored cells */
.cell-present .cell-in  { color: #064e3b; background: rgba(255,255,255,.4); }
.cell-present .cell-out { color: #7f1d1d; background: rgba(255,255,255,.3); }
.cell-absent  .cell-in,
.cell-absent  .cell-out { color: #fff; background: rgba(255,255,255,.2); }
.cell-half    .cell-in,
.cell-half    .cell-out { color: #451a03; background: rgba(255,255,255,.35); }
.cell-late    .cell-in,
.cell-late    .cell-out { color: #1e293b; background: rgba(255,255,255,.45); }
.cell-leave   .cell-in,
.cell-leave   .cell-out { color: #1e3a5f; background: rgba(255,255,255,.35); }

.attendance-empty .cell-label {
    font-weight: 400;
}

/* Status color cells */
.cell-present  { background: #d1fae5; color: #065f46; border-radius: 4px; }
.cell-absent   { background: #fee2e2; color: #991b1b; border-radius: 4px; }
.cell-half     { background: #fef9c3; color: #854d0e; border-radius: 4px; }
.cell-late     { background: #f1f5f9; color: #475569; border-radius: 4px; }
.cell-leave    { background: #dbeafe; color: #1e40af; border-radius: 4px; }

/* Employee avatar */
.employee-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Legend dot */
.legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
}
</style>
