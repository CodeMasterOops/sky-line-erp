<template>
    <PageHeader title="TDS Salary Report" subtitle="Employee-wise TDS deducted on salary" />

    <section class="section">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Fiscal Year</label>
                        <select v-model="filter.fiscal_year_id" class="form-select">
                            <option value="">-- Select Fiscal Year --</option>
                            <option v-for="fy in fiscalYears" :key="fy.id" :value="fy.id">{{ fy.year_name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100" @click="loadReport" :disabled="!filter.fiscal_year_id || loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <template v-if="report">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="text-muted">Total Gross (TDS Employees)</div>
                            <strong class="fs-5">{{ report.summary.total_gross?.toFixed(2) }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="text-muted">Total TDS Withheld</div>
                            <strong class="fs-5 text-danger">{{ report.summary.total_tds?.toFixed(2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div v-if="!report.data.length" class="text-center text-muted py-4">
                        No TDS deductions found for the selected fiscal year.
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Employee</th>
                                    <th>PAN</th>
                                    <th>TDS Category</th>
                                    <th class="text-end">Rate (%)</th>
                                    <th class="text-end">Total Gross</th>
                                    <th class="text-end">Total TDS</th>
                                    <th>Months</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in report.data" :key="row.employee_code">
                                    <td>{{ row.employee_code }}</td>
                                    <td>{{ row.full_name }}</td>
                                    <td>{{ row.pan || '—' }}</td>
                                    <td class="small">{{ row.tds_category_label || '—' }}</td>
                                    <td class="text-end">{{ row.tds_rate }}</td>
                                    <td class="text-end">{{ row.total_gross?.toFixed(2) }}</td>
                                    <td class="text-end text-danger fw-semibold">{{ row.total_tds?.toFixed(2) }}</td>
                                    <td>
                                        <span v-for="m in row.months" :key="m.month" class="badge bg-light text-dark me-1 border">
                                            {{ m.month_label }}: {{ m.tds_amount?.toFixed(2) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="5">Total</td>
                                    <td class="text-end">{{ report.summary.total_gross?.toFixed(2) }}</td>
                                    <td class="text-end text-danger">{{ report.summary.total_tds?.toFixed(2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const filter = ref({ fiscal_year_id: '' });
const loading = ref(false);
const report = ref(null);
const fiscalYears = ref([]);

onMounted(async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year');
        fiscalYears.value = res.data.data ?? [];
        const current = fiscalYears.value.find(f => f.is_current);
        if (current) filter.value.fiscal_year_id = current.id;
    } catch { /* ignore */ }
});

const loadReport = async () => {
    if (!filter.value.fiscal_year_id) return;
    loading.value = true;
    report.value = null;
    try {
        const params = new URLSearchParams({ fiscal_year_id: filter.value.fiscal_year_id }).toString();
        const res = await apiAdmin(`hr/report/tds-salary?${params}`);
        report.value = res.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};
</script>
