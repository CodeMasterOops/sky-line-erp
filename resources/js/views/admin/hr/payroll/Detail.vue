<template>
    <PageHeader title="Payroll Detail" :subtitle="run.data?.month_year_label">
        <template #actions>
            <button v-if="run.data?.status !== 'paid'" type="button" @click="processRun" class="btn btn-primary me-2">
                <i class="ti ti-calculator me-1"></i> Calculate
            </button>
            <button v-if="run.data?.status === 'processed'" type="button" @click="confirmRun" class="btn btn-success">
                <i class="ti ti-check me-1"></i> Confirm Paid
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <VLoader v-if="run.loading" :colspan="7" />
        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="text-muted">Status</div>
                            <span :class="statusBadge(run.data?.status)">{{ run.data?.status_label }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="text-muted">Total Gross</div>
                            <strong>{{ run.data?.total_gross }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="text-muted">Deductions</div>
                            <strong>{{ run.data?.total_deductions }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="text-muted">Net Pay</div>
                            <strong>{{ run.data?.total_net }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Working Days</th>
                                    <th>Present Days</th>
                                    <th>Gross</th>
                                    <th>Deductions</th>
                                    <th>Net Pay</th>
                                    <th class="text-center">Payslip</th>
                                </tr>
                            </thead>
                            <tbody class="align-middle">
                                <tr v-for="p in run.data?.payslips" :key="p.id">
                                    <td>{{ p.employee?.full_name }}</td>
                                    <td>{{ p.working_days }}</td>
                                    <td>{{ p.present_days }}</td>
                                    <td>{{ p.gross_salary }}</td>
                                    <td>{{ p.total_deductions }}</td>
                                    <td>{{ p.net_salary }}</td>
                                    <td class="text-center">
                                        <router-link :to="{ name: 'admin.hr-payslip', params: { id: p.id } }" class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-file"></i>
                                        </router-link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import Swal from 'sweetalert2';
import { storeToRefs } from 'pinia';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';

const route = useRoute();
const payrollStore = usePayrollStore();
const { run } = storeToRefs(payrollStore);

onMounted(() => payrollStore.getRun(route.params.id));

const statusBadge = (s) => ({ draft: 'badge bg-secondary', processed: 'badge bg-primary', paid: 'badge bg-success' }[s?.value ?? s] ?? 'badge bg-secondary');

const processRun = async () => {
    try { const res = await payrollStore.processRun(route.params.id); toast(res.status, res.data.message); }
    catch (e) { showErrors(e); }
};

const confirmRun = async () => {
    const result = await Swal.fire({ title: 'Confirm payroll as Paid?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes' });
    if (result.value) {
        try { const res = await payrollStore.confirmRun(route.params.id); toast(res.status, res.data.message); }
        catch (e) { showErrors(e); }
    }
};
</script>
