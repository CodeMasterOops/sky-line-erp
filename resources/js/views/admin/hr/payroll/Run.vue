<template>
    <PageHeader title="Payroll Runs" subtitle="Process monthly payroll" @refresh="payrollStore.getRuns()">
        <template #actions>
            <button type="button" @click="openCreateModal" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> New Payroll Run
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Total Gross</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="runs.loading" :colspan="7" />
                            <template v-else-if="runs.data.length">
                                <tr v-for="(r, i) in runs.data" :key="r.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ r.period_label }}</td>
                                    <td><span :class="statusBadge(r.status)">{{ r.status_label }}</span></td>
                                    <td>{{ r.total_gross }}</td>
                                    <td>{{ r.total_deductions }}</td>
                                    <td>{{ r.total_net }}</td>
                                    <td style="width:170px;" class="text-center">
                                        <router-link :to="{ name: 'admin.hr-payroll-detail', params: { id: r.id } }" class="btn btn-sm btn-outline-info me-1"><i class="ti ti-eye"></i></router-link>
                                        <button v-if="r.status !== 'paid'" type="button" @click="processRun(r.id)" class="btn btn-sm btn-outline-primary me-1" title="Calculate"><i class="ti ti-calculator"></i></button>
                                        <button v-if="r.status === 'processed'" type="button" @click="confirmRun(r.id)" class="btn btn-sm btn-outline-success me-1" title="Mark Paid"><i class="ti ti-check"></i></button>
                                        <button v-if="r.status !== 'paid'" type="button" @click="deleteRun(r.id)" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else><td colspan="7" class="text-center">No payroll runs found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <VModal :show-modal="showCreateModal" @close-click="showCreateModal = false" title="Create Payroll Run">
        <template #modal-body>
            <form @submit.prevent="storeRun" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Fiscal Year</label>
                    <div v-if="currentFiscalYear" class="form-control bg-light text-muted">
                        {{ currentFiscalYear.year_name }}
                        <span class="badge bg-success ms-2">Current</span>
                    </div>
                    <div v-else class="alert alert-warning mb-0 py-2">
                        No current fiscal year set. Please ask the super admin to set one.
                    </div>
                </div>
                <div class="col-12" v-if="currentFiscalYear">
                    <label class="form-label">Month</label>
                    <select v-model="cForm.month" class="form-select">
                        <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="showCreateModal = false" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="cSubmitting" :disabled="!currentFiscalYear" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';
import { apiAdmin } from '@/helpers/api.js';

const payrollStore = usePayrollStore();
const { runs } = storeToRefs(payrollStore);
const showCreateModal = ref(false);
const cSubmitting = ref(false);
const fiscalYears = ref([]);

const now = new Date();
const cForm = reactive({ fiscal_year_id: null, month: now.getMonth() + 1 });

const currentFiscalYear = computed(() => fiscalYears.value.find(fy => fy.is_current) ?? null);

const monthName = (m) => new Date(2000, m - 1, 1).toLocaleString('default', { month: 'long' });
const statusBadge = (s) => ({ draft: 'badge bg-secondary', processed: 'badge bg-primary', paid: 'badge bg-success' }[s?.value ?? s] ?? 'badge bg-secondary');

const loadFiscalYears = async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year');
        fiscalYears.value = res.data.data;
    } catch (e) {
        showErrors(e);
    }
};

const openCreateModal = () => {
    if (currentFiscalYear.value) {
        cForm.fiscal_year_id = currentFiscalYear.value.id;
    }
    showCreateModal.value = true;
};

onMounted(() => {
    payrollStore.getRuns();
    loadFiscalYears();
});

const storeRun = async () => {
    if (!currentFiscalYear.value) return;
    cForm.fiscal_year_id = currentFiscalYear.value.id;
    cSubmitting.value = true;
    try {
        const res = await payrollStore.storeRun(cForm);
        toast(res.status, res.data.message);
        showCreateModal.value = false;
    } catch (e) {
        showErrors(e);
    } finally {
        cSubmitting.value = false;
    }
};

const processRun = async (id) => {
    try { const res = await payrollStore.processRun(id); toast(res.status, res.data.message); }
    catch (e) { showErrors(e); }
};

const confirmRun = async (id) => {
    const result = await Swal.fire({ title: 'Mark payroll as Paid?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Confirm' });
    if (result.value) {
        try { const res = await payrollStore.confirmRun(id); toast(res.status, res.data.message); }
        catch (e) { showErrors(e); }
    }
};

const deleteRun = (id) => {
    Swal.fire({ title: 'Delete payroll run?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => { if (r.value) { try { const res = await payrollStore.deleteRun(id); toast(res.status, res.data.message); } catch (e) { showErrors(e); } } });
};
</script>
