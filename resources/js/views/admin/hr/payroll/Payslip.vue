<template>
    <PageHeader title="Salary Slip" :subtitle="payslip.data?.employee?.full_name">
        <template #actions>
            <DocumentPrintButton
                target="#document-print-area"
                :title="`Salary Slip - ${payslip.data?.employee?.full_name || ''}`"
                label="Print"
            />
        </template>
    </PageHeader>

    <section class="section">
        <VLoader v-if="payslip.loading" :colspan="3" />
        <DocumentPrintLayout
            v-else
            document-title="Salary Slip"
            :document-no="payslipNo"
            :document-date="periodLabel"
        >
            <template #header-meta>
                <p class="mb-1 fw-medium">
                    Pay Period: <span class="text-dark">{{ periodLabel }}</span>
                </p>
                <p v-if="payDate" class="mb-1 fw-medium">
                    Pay Date: <span class="text-dark">{{ payDate }}</span>
                </p>
                <p class="mb-0 no-print">
                    <span :class="statusBadge(payslip.data?.payroll_run?.status)">
                        {{ payslip.data?.payroll_run?.status_label }}
                    </span>
                </p>
            </template>

            <template #parties>
                <div class="col-md-6">
                    <p class="text-dark mb-2 fw-semibold">Employee</p>
                    <h4 class="mb-1">{{ payslip.data?.employee?.full_name }}</h4>
                    <p class="mb-1">Code: <span class="text-dark">{{ payslip.data?.employee?.employee_code || '—' }}</span></p>
                    <p class="mb-1">Department: <span class="text-dark">{{ payslip.data?.employee?.department?.name || '—' }}</span></p>
                    <p class="mb-1">Designation: <span class="text-dark">{{ payslip.data?.employee?.designation?.name || '—' }}</span></p>
                    <p v-if="joinDate" class="mb-1">Date of Joining: <span class="text-dark">{{ joinDate }}</span></p>
                    <p v-if="payslip.data?.employee?.pan" class="mb-0">PAN: <span class="text-dark">{{ payslip.data.employee.pan }}</span></p>
                </div>
                <div class="col-md-6">
                    <p class="text-dark mb-2 fw-semibold">Payment &amp; Attendance</p>
                    <p v-if="payslip.data?.employee?.bank_name" class="mb-1">Bank: <span class="text-dark">{{ payslip.data.employee.bank_name }}</span></p>
                    <p v-if="payslip.data?.employee?.bank_account_no" class="mb-1">A/C No: <span class="text-dark">{{ payslip.data.employee.bank_account_no }}</span></p>
                    <p class="mb-1">Working Days: <span class="text-dark">{{ payslip.data?.working_days }}</span></p>
                    <p class="mb-1">Present Days: <span class="text-dark">{{ payslip.data?.present_days }}</span></p>
                    <p v-if="(payslip.data?.half_days ?? 0) > 0" class="mb-1">Half Days: <span class="text-dark">{{ payslip.data?.half_days }}</span></p>
                    <p class="mb-1">Leave Days: <span class="text-dark">{{ payslip.data?.leave_days }}</span></p>
                    <p class="mb-0">Absent Days: <span class="text-dark">{{ payslip.data?.absent_days }}</span></p>
                </div>
            </template>

            <template #body>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered mb-3">
                            <thead class="table-success"><tr><th colspan="2">Earnings</th></tr></thead>
                            <tbody>
                                <tr v-for="item in earnings" :key="item.id">
                                    <td>{{ item.component_name }}</td>
                                    <td class="text-end">{{ formatMoney(item.amount) }}</td>
                                </tr>
                                <tr class="fw-bold"><td>Gross Earnings</td><td class="text-end">{{ formatMoney(payslip.data?.gross_salary) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered mb-3">
                            <thead class="table-danger"><tr><th colspan="2">Deductions</th></tr></thead>
                            <tbody>
                                <tr v-for="item in deductions" :key="item.id">
                                    <td>{{ item.component_name }}</td>
                                    <td class="text-end">{{ formatMoney(item.amount) }}</td>
                                </tr>
                                <tr v-if="(payslip.data?.tds_amount ?? 0) > 0">
                                    <td>TDS Withheld <span class="text-muted small">({{ payslip.data?.employee?.tds_category_label }})</span></td>
                                    <td class="text-end text-danger">{{ formatMoney(payslip.data?.tds_amount) }}</td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Total Deductions</td>
                                    <td class="text-end">{{ formatMoney(totalDeductions) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="employerContributions.length" class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-info"><tr><th colspan="2">Employer Contributions <span class="fw-normal small">(not deducted from pay)</span></th></tr></thead>
                            <tbody>
                                <tr v-for="item in employerContributions" :key="item.id">
                                    <td>{{ item.component_name }}</td>
                                    <td class="text-end">{{ formatMoney(item.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <template #totals>
                <div class="col-12">
                    <div class="alert alert-primary d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold">Net Pay</span>
                        <span class="fs-4 fw-bold">{{ formatMoney(payslip.data?.net_salary) }}</span>
                    </div>
                    <DocumentPrintAmountWords :amount="payslip.data?.net_salary" label="Net Pay in words" />
                </div>
            </template>

            <template #footer>
                <div class="row mt-5 pt-4">
                    <div class="col-6">
                        <div class="border-top pt-2 text-muted small">Prepared By</div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="border-top pt-2 text-muted small">Employee Signature</div>
                    </div>
                </div>
                <p class="text-muted small text-center mt-3 mb-0">
                    This is a computer-generated salary slip and does not require a signature.
                </p>
            </template>
        </DocumentPrintLayout>
    </section>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, onMounted} from 'vue';
import {useRoute} from 'vue-router';
import {storeToRefs} from 'pinia';
import {usePayrollStore} from '@/stores/admin/hr/payroll.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import {useDisplayDate} from '@/composables/useDisplayDate.js';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';
import DocumentPrintAmountWords from '@/components/print/DocumentPrintAmountWords.vue';

const route = useRoute();
const payrollStore = usePayrollStore();
const {payslip} = storeToRefs(payrollStore);
const {ensureBranding} = useCompanyBranding();
const {formatDate, formatDateTime} = useDisplayDate();

onMounted(async () => {
    await ensureBranding();
    payrollStore.getPayslip(route.params.id);
});

const earnings = computed(() => payslip.value.data?.items?.filter(i => i.component_type === 'earning') ?? []);
const deductions = computed(() => payslip.value.data?.items?.filter(i => i.component_type === 'deduction') ?? []);
const employerContributions = computed(() => payslip.value.data?.items?.filter(i => i.component_type === 'employer_contribution') ?? []);
const totalDeductions = computed(() => {
    const ded = Number(payslip.value.data?.total_deductions ?? 0);
    const tds = Number(payslip.value.data?.tds_amount ?? 0);
    return (ded + tds).toFixed(2);
});
const periodLabel = computed(() => payslip.value.data?.payroll_run?.month_year_label || '');
const payslipNo = computed(() => payslip.value.data?.id ? `PS-${payslip.value.data.id}` : '');
const payDate = computed(() => formatDateTime(payslip.value.data?.payroll_run?.paid_at));
const joinDate = computed(() => formatDate(payslip.value.data?.employee?.join_date));
const statusBadge = (s) => ({draft: 'badge bg-secondary', processed: 'badge bg-primary', paid: 'badge bg-success'}[s?.value ?? s] ?? 'badge bg-secondary');
</script>
