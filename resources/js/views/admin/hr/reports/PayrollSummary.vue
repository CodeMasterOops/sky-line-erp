<template>
    <PageHeader hide-action-buttons title="Payroll Summary" subtitle="Monthly payroll summary report" />

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <VMultiselect
                            id="fiscal_year_id"
                            v-model="fiscalYearId"
                            label="Fiscal Year"
                            placeholder="Select fiscal year"
                            :options="fiscalYearOptions"
                            name-prop="year_name"
                            :disabled="loadingFY"
                        />
                    </div>
                    <div class="col-md-2">
                        <button type="button" @click="load" :disabled="!fiscalYearId" class="btn btn-primary w-100">Generate</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <VLoader v-if="loading" :colspan="6" />
                <div v-else class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Employees</th>
                                <th>Status</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data" :key="row.month">
                                <td>{{ row.month_label }}</td>
                                <td>{{ row.employee_count }}</td>
                                <td>{{ row.status }}</td>
                                <td>{{ formatMoney(row.total_gross) }}</td>
                                <td>{{ formatMoney(row.total_deductions) }}</td>
                                <td>{{ formatMoney(row.total_net) }}</td>
                            </tr>
                            <tr v-if="!data.length"><td colspan="6" class="text-center">No data found.</td></tr>
                        </tbody>
                        <tfoot v-if="data.length" class="fw-bold">
                            <tr>
                                <td colspan="3">Total</td>
                                <td>{{ formatMoney(totalGross) }}</td>
                                <td>{{ formatMoney(totalDed) }}</td>
                                <td>{{ formatMoney(totalNet) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import { ref, computed, onMounted } from 'vue';
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
        const res = await apiAdmin('hr/report/payroll-summary', 'get', { fiscal_year_id: fiscalYearId.value });
        data.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(loadFiscalYears);

const totalGross = computed(() => data.value.reduce((s, r) => s + r.total_gross, 0).toFixed(2));
const totalDed = computed(() => data.value.reduce((s, r) => s + r.total_deductions, 0).toFixed(2));
const totalNet = computed(() => data.value.reduce((s, r) => s + r.total_net, 0).toFixed(2));

const fiscalYearOptions = computed(() =>
    fiscalYears.value.map(fy => ({
        ...fy,
        year_name: fy.is_current ? `${fy.year_name} (Current)` : fy.year_name,
    }))
);
</script>
