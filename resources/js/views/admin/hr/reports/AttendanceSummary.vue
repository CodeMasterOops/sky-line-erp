<template>
    <PageHeader hide-action-buttons title="Attendance Summary" subtitle="Monthly attendance report" />

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <VMultiselect
                            id="fiscal_year_id"
                            v-model="filters.fiscal_year_id"
                            label="Fiscal Year"
                            placeholder="Select fiscal year"
                            :options="fiscalYearOptions"
                            name-prop="year_name"
                            :disabled="loadingFY"
                        />
                    </div>
                    <div class="col-md-3">
                        <VMultiselect
                            id="month"
                            v-model="filters.month"
                            label="Month"
                            :options="monthOptions"
                        />
                    </div>
                    <div class="col-md-2">
                        <button type="button" @click="load" :disabled="!filters.fiscal_year_id" class="btn btn-primary w-100">Generate</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <VLoader v-if="loading" :colspan="7" />
                <div v-else class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Half Day</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">On Leave</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data" :key="row.employee_code">
                                <td>{{ row.full_name }} <small class="text-muted">({{ row.employee_code }})</small></td>
                                <td class="text-center"><span class="badge bg-success">{{ row.present }}</span></td>
                                <td class="text-center"><span class="badge bg-danger">{{ row.absent }}</span></td>
                                <td class="text-center"><span class="badge bg-warning text-dark">{{ row.half_day }}</span></td>
                                <td class="text-center"><span class="badge bg-secondary">{{ row.late }}</span></td>
                                <td class="text-center"><span class="badge bg-info">{{ row.on_leave }}</span></td>
                            </tr>
                            <tr v-if="!data.length"><td colspan="6" class="text-center">No data found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors';

const fiscalYears = ref([]);
const loadingFY = ref(false);
const now = new Date();
const filters = reactive({ fiscal_year_id: '', month: now.getMonth() + 1 });
const loading = ref(false);
const data = ref([]);

const monthName = (m) => new Date(2000, m - 1, 1).toLocaleString('default', { month: 'long' });

const monthOptions = Array.from({ length: 12 }, (_, i) => ({
    id: i + 1,
    name: monthName(i + 1),
}));

const fiscalYearOptions = computed(() =>
    fiscalYears.value.map(fy => ({
        ...fy,
        year_name: fy.is_current ? `${fy.year_name} (Current)` : fy.year_name,
    }))
);

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
    }
};

const load = async () => {
    if (!filters.fiscal_year_id) return;
    loading.value = true;
    try {
        const res = await apiAdmin('hr/report/attendance-summary', 'get', filters);
        data.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(loadFiscalYears);
</script>
