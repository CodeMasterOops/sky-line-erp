<template>
    <PageHeader title="Payslip" :subtitle="payslip.data?.employee?.full_name">
        <template #actions>
            <button type="button" @click="printPayslip" class="btn btn-outline-primary d-flex align-items-center">
                <i class="ti ti-printer me-2"></i> Print
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <VLoader v-if="payslip.loading" :colspan="3" />
        <div v-else class="card" id="payslip-card">
            <div class="card-body p-5">
                <div class="d-flex justify-content-between mb-4">
                    <div>
                        <h4 class="mb-1">Payslip</h4>
                        <p class="text-muted mb-0">{{ payslip.data?.payroll_run?.month_year_label }}</p>
                    </div>
                    <div class="text-end">
                        <p class="mb-0"><strong>Status: </strong><span :class="statusBadge(payslip.data?.payroll_run?.status)">{{ payslip.data?.payroll_run?.status_label }}</span></p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Employee Details</h6>
                        <p class="mb-1"><strong>{{ payslip.data?.employee?.full_name }}</strong></p>
                        <p class="mb-1">Code: {{ payslip.data?.employee?.employee_code }}</p>
                        <p class="mb-1">Department: {{ payslip.data?.employee?.department?.name }}</p>
                        <p class="mb-1">Designation: {{ payslip.data?.employee?.designation?.name }}</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h6 class="text-muted">Attendance Summary</h6>
                        <p class="mb-1">Working Days: {{ payslip.data?.working_days }}</p>
                        <p class="mb-1">Present Days: {{ payslip.data?.present_days }}</p>
                        <p class="mb-1">Leave Days: {{ payslip.data?.leave_days }}</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <thead class="table-success"><tr><th colspan="2">Earnings</th></tr></thead>
                            <tbody>
                                <tr v-for="item in earnings" :key="item.id">
                                    <td>{{ item.component_name }}</td>
                                    <td class="text-end">{{ item.amount }}</td>
                                </tr>
                                <tr class="fw-bold"><td>Gross</td><td class="text-end">{{ payslip.data?.gross_salary }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <thead class="table-danger"><tr><th colspan="2">Deductions</th></tr></thead>
                            <tbody>
                                <tr v-for="item in deductions" :key="item.id">
                                    <td>{{ item.component_name }}</td>
                                    <td class="text-end">{{ item.amount }}</td>
                                </tr>
                                <tr v-if="(payslip.data?.tds_amount ?? 0) > 0">
                                    <td>TDS Withheld <span class="text-muted small">({{ payslip.data?.employee?.tds_category_label }})</span></td>
                                    <td class="text-end text-danger">{{ payslip.data?.tds_amount }}</td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Total Deductions</td>
                                    <td class="text-end">{{ totalDeductions }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-primary d-flex justify-content-between align-items-center">
                            <span class="fs-5 fw-bold">Net Pay</span>
                            <span class="fs-4 fw-bold">{{ payslip.data?.net_salary }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="payslip.data?.employee?.pan" class="row mt-2">
                    <div class="col-12 text-muted small">
                        Employee PAN: <strong>{{ payslip.data.employee.pan }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';

const route = useRoute();
const payrollStore = usePayrollStore();
const { payslip } = storeToRefs(payrollStore);

onMounted(() => payrollStore.getPayslip(route.params.id));

const earnings = computed(() => payslip.value.data?.items?.filter(i => i.component_type === 'earning') ?? []);
const deductions = computed(() => payslip.value.data?.items?.filter(i => i.component_type === 'deduction') ?? []);
const totalDeductions = computed(() => {
    const ded = Number(payslip.value.data?.total_deductions ?? 0);
    const tds = Number(payslip.value.data?.tds_amount ?? 0);
    return (ded + tds).toFixed(2);
});
const statusBadge = (s) => ({ draft: 'badge bg-secondary', processed: 'badge bg-primary', paid: 'badge bg-success' }[s?.value ?? s] ?? 'badge bg-secondary');
const printPayslip = () => window.print();
</script>
