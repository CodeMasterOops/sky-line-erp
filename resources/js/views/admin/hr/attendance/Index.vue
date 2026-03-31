<template>
    <PageHeader title="Attendance" subtitle="Track employee attendance">
        <template #actions>
            <button type="button" @click="showBulkModal = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-calendar-plus me-2"></i> Mark Attendance
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Fiscal Year</label>
                        <select v-model="filters.fiscal_year_id" @change="onFiscalYearChange" class="form-select" :disabled="loadingFY">
                            <option value="">Select fiscal year</option>
                            <option v-for="fy in fiscalYears" :key="fy.id" :value="fy.id">
                                {{ fy.year_name }}{{ fy.is_current ? ' (Current)' : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select v-model="filters.month" @change="loadSheet" class="form-select">
                            <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <VLoader v-if="monthlySheet.loading" :colspan="5" />
                <div v-else class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th v-for="day in daysInMonth" :key="day" class="text-center" style="min-width:36px;">{{ day }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in monthlySheet.data" :key="row.employee.id">
                                <td class="text-nowrap">{{ row.employee.full_name }}</td>
                                <td v-for="day in daysInMonth" :key="day" class="text-center p-0">
                                    <span :class="cellClass(row.attendances[day]?.status)" :title="row.attendances[day]?.status_label">
                                        {{ cellLabel(row.attendances[day]?.status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-2 d-flex gap-3 flex-wrap">
                        <span class="badge bg-success">P = Present</span>
                        <span class="badge bg-warning text-dark">H = Half Day</span>
                        <span class="badge bg-danger">A = Absent</span>
                        <span class="badge bg-info">L = On Leave</span>
                        <span class="badge bg-secondary">La = Late</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <BulkAttendanceModal v-model:show="showBulkModal" :month="filters.month" :year="selectedYear" @saved="loadSheet" />
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useAttendanceStore } from '@/stores/admin/hr/attendance.js';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors';
import BulkAttendanceModal from './BulkModal.vue';

const store = useAttendanceStore();
const { monthlySheet } = storeToRefs(store);
const showBulkModal = ref(false);
const now = new Date();
const fiscalYears = ref([]);
const loadingFY = ref(false);
const filters = reactive({ fiscal_year_id: '', month: now.getMonth() + 1 });

const selectedYear = computed(() => {
    const fy = fiscalYears.value.find(f => f.id === filters.fiscal_year_id);
    return fy ? new Date(fy.start_date).getFullYear() : now.getFullYear();
});

const daysInMonth = computed(() => {
    const days = new Date(selectedYear.value, filters.month, 0).getDate();
    return Array.from({ length: days }, (_, i) => String(i + 1).padStart(2, '0'));
});

const monthName = (m) => new Date(2000, m - 1, 1).toLocaleString('default', { month: 'long' });

const loadFiscalYears = async () => {
    loadingFY.value = true;
    try {
        const res = await apiAdmin('admin-setting/fiscal-year');
        fiscalYears.value = res.data.data;
        const current = fiscalYears.value.find(fy => fy.is_current);
        if (current) filters.fiscal_year_id = current.id;
    } catch (e) {
        showErrors(e);
    } finally {
        loadingFY.value = false;
        loadSheet();
    }
};

const onFiscalYearChange = () => loadSheet();

const loadSheet = () => {
    store.getMonthlySheet({ month: filters.month, year: selectedYear.value });
};

onMounted(loadFiscalYears);

const cellLabel = (status) => ({ present: 'P', absent: 'A', half_day: 'H', late: 'La', on_leave: 'L' }[status?.value ?? status] ?? '');
const cellClass = (status) => ({
    present: 'badge bg-success d-block w-100',
    absent: 'badge bg-danger d-block w-100',
    half_day: 'badge bg-warning text-dark d-block w-100',
    late: 'badge bg-secondary d-block w-100',
    on_leave: 'badge bg-info d-block w-100',
}[status?.value ?? status] ?? '');
</script>
