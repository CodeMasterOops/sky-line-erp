<template>
    <PageHeader :title="ledger.employee ? `Salary Ledger — ${ledger.employee.full_name}` : 'Salary Ledger'"
        :subtitle="ledger.employee?.employee_code" />

    <section class="section">
        <div class="card mb-3">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-4">
                    <VMultiselect
                        id="fiscal_year_id"
                        v-model="fiscalYearId"
                        label="Fiscal Year"
                        :options="fiscalYearOptions"
                        name-prop="year_name"
                    />
                </div>
                <div class="col-md-8 text-md-end">
                    <router-link :to="{ name: 'admin.hr-employee-list' }" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Back to Employees
                    </router-link>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <VLoader v-if="loading" :colspan="8" />
                <div v-else class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">TDS</th>
                                <th class="text-end">Net</th>
                                <th class="text-end">YTD Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.payslip_id">
                                <td>
                                    <router-link :to="{ name: 'admin.hr-payslip', params: { id: row.payslip_id } }">
                                        {{ row.period_label }}
                                    </router-link>
                                </td>
                                <td>{{ row.status_label }}</td>
                                <td class="text-end">{{ formatMoney(row.gross_salary) }}</td>
                                <td class="text-end">{{ formatMoney(row.total_deductions) }}</td>
                                <td class="text-end">{{ formatMoney(row.tds_amount) }}</td>
                                <td class="text-end">{{ formatMoney(row.net_salary) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoney(row.ytd_net) }}</td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="7" class="text-center text-muted py-4">No payroll records for this fiscal year.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length">
                            <tr class="fw-bold table-light">
                                <td colspan="2">Total</td>
                                <td class="text-end">{{ formatMoney(totals.gross) }}</td>
                                <td class="text-end">{{ formatMoney(totals.deductions) }}</td>
                                <td class="text-end">{{ formatMoney(totals.tds) }}</td>
                                <td class="text-end">{{ formatMoney(totals.net) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiAdmin } from '@/helpers/api.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import showErrors from '@/helpers/showErrors';

const route = useRoute();
const loading = ref(false);
const fiscalYears = ref([]);
const fiscalYearId = ref(null);
const rows = ref([]);
const totals = reactive({ gross: 0, deductions: 0, tds: 0, net: 0 });
const ledger = reactive({ employee: null });

const fiscalYearOptions = computed(() =>
    fiscalYears.value.map(fy => ({
        ...fy,
        year_name: fy.is_current ? `${fy.year_name} (Current)` : fy.year_name,
    }))
);

watch(fiscalYearId, () => fetchLedger());

const fetchLedger = async () => {
    if (!fiscalYearId.value) return;
    loading.value = true;
    try {
        const res = await apiAdmin(`hr/employee/${route.params.id}/salary-ledger`, 'get', { fiscal_year_id: fiscalYearId.value });
        rows.value = res.data.rows ?? [];
        Object.assign(totals, res.data.totals ?? { gross: 0, deductions: 0, tds: 0, net: 0 });
        ledger.employee = res.data.employee;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year');
        fiscalYears.value = res.data.data ?? [];
        fiscalYearId.value = (fiscalYears.value.find(fy => fy.is_current) ?? fiscalYears.value[0])?.id ?? null;
        await fetchLedger();
    } catch (e) {
        showErrors(e);
    }
});
</script>
