<template>
    <PageHeader title="Leave Balance" subtitle="Employee leave balance report" />

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Fiscal Year</label>
                        <select v-model="fiscalYearId" class="form-select" :disabled="loadingFY">
                            <option value="">Select fiscal year</option>
                            <option v-for="fy in fiscalYears" :key="fy.id" :value="fy.id">
                                {{ fy.year_name }}{{ fy.is_current ? ' (Current)' : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" @click="load" :disabled="!fiscalYearId" class="btn btn-primary w-100">Generate</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <VLoader v-if="loading" :colspan="3" />
                <div v-else>
                    <div v-for="emp in data" :key="emp.employee_code" class="mb-4">
                        <h6 class="fw-bold">{{ emp.full_name }} <small class="text-muted">({{ emp.employee_code }})</small></h6>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Leave Type</th>
                                    <th class="text-center">Allowed</th>
                                    <th class="text-center">Used</th>
                                    <th class="text-center">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in emp.leave_summary" :key="l.leave_type">
                                    <td>{{ l.leave_type }}</td>
                                    <td class="text-center">{{ l.days_allowed }}</td>
                                    <td class="text-center">{{ l.days_used }}</td>
                                    <td class="text-center">
                                        <span :class="l.days_remaining >= 0 ? 'badge bg-success' : 'badge bg-danger'">{{ l.days_remaining }}</span>
                                    </td>
                                </tr>
                                <tr v-if="!emp.leave_summary.length">
                                    <td colspan="4" class="text-muted">No leave taken</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!data.length" class="text-center text-muted">No data found.</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors';

const fiscalYears = ref([]);
const fiscalYearId = ref('');
const loadingFY = ref(false);
const loading = ref(false);
const data = ref([]);

const loadFiscalYears = async () => {
    loadingFY.value = true;
    try {
        const res = await apiAdmin('admin-setting/fiscal-year');
        fiscalYears.value = res.data.data;
        const current = fiscalYears.value.find(fy => fy.is_current);
        if (current) fiscalYearId.value = current.id;
    } catch (e) {
        showErrors(e);
    } finally {
        loadingFY.value = false;
    }
};

const load = async () => {
    if (!fiscalYearId.value) return;
    loading.value = true;
    try {
        const res = await apiAdmin('hr/report/leave-balance', 'get', { fiscal_year_id: fiscalYearId.value });
        data.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(loadFiscalYears);
</script>
