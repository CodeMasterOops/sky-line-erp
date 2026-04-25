<template>
    <PageHeader title="Payroll Detail" :subtitle="run.data?.month_year_label">
        <template #actions>
            <button v-if="run.data?.status !== 'paid'" type="button" @click="processRun" class="btn btn-primary me-2">
                <i class="ti ti-calculator me-1"></i> Calculate
            </button>
            <button v-if="run.data?.status === 'processed'" type="button" @click="showConfirmModal = true" class="btn btn-success">
                <i class="ti ti-check me-1"></i> Confirm & Pay
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

            <div v-if="run.data?.status === 'paid' && run.data?.paid_account" class="alert alert-success d-flex align-items-center gap-2">
                <i class="ti ti-circle-check"></i>
                Payroll posted to ledger. Paid from account: <strong>{{ run.data.paid_account?.name }}</strong>.
                <router-link v-if="run.data?.journal_id" :to="{ name: 'admin.journal-voucher-list' }" class="ms-auto btn btn-sm btn-outline-success">View Journal</router-link>
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
                                    <th>TDS</th>
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
                                    <td>{{ p.tds_amount ?? 0 }}</td>
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

    <!-- Confirm & Pay Modal -->
    <VModal :show-modal="showConfirmModal" @close-click="showConfirmModal = false" title="Confirm Payroll as Paid">
        <template #modal-body>
            <div class="row g-3">
                <div class="col-12">
                    <p class="text-muted">Select the bank or cash account from which salaries will be paid. A journal entry will be automatically posted to the ledger.</p>
                </div>
                <div class="col-12">
                    <label class="form-label">Payment Account <span class="text-danger">*</span></label>
                    <select v-model="paidAccountId" class="form-select">
                        <option value="">-- Select Account --</option>
                        <option v-for="acc in accountList" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                    <div v-if="confirmError" class="text-danger small mt-1">{{ confirmError }}</div>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="showConfirmModal = false" class="btn btn-danger me-2">Cancel</button>
                    <button type="button" @click="confirmRun" :disabled="isConfirming" class="btn btn-success">
                        <span v-if="isConfirming" class="spinner-border spinner-border-sm me-1"></span>
                        Confirm & Post to Ledger
                    </button>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';
import { apiAdmin } from '@/helpers/api.js';

const route = useRoute();
const payrollStore = usePayrollStore();
const { run } = storeToRefs(payrollStore);

const showConfirmModal = ref(false);
const paidAccountId = ref('');
const confirmError = ref('');
const isConfirming = ref(false);
const accountList = ref([]);

onMounted(async () => {
    payrollStore.getRun(route.params.id);
    try {
        const res = await apiAdmin('account?limit=200');
        accountList.value = res.data.data ?? [];
    } catch { /* ignore */ }
});

const statusBadge = (s) => ({ draft: 'badge bg-secondary', processed: 'badge bg-primary', paid: 'badge bg-success' }[s?.value ?? s] ?? 'badge bg-secondary');

const processRun = async () => {
    try { const res = await payrollStore.processRun(route.params.id); toast(res.status, res.data.message); }
    catch (e) { showErrors(e); }
};

const confirmRun = async () => {
    confirmError.value = '';
    if (!paidAccountId.value) {
        confirmError.value = 'Please select a payment account.';
        return;
    }
    isConfirming.value = true;
    try {
        const res = await payrollStore.confirmRun(route.params.id, { paid_account_id: paidAccountId.value });
        toast(res.status, res.data.message);
        showConfirmModal.value = false;
        paidAccountId.value = '';
    } catch (e) { showErrors(e); }
    finally { isConfirming.value = false; }
};
</script>
