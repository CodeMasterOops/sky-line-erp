<template>
    <PageHeader title="Payroll Runs" subtitle="Process monthly payroll" @refresh="fetchRuns">
        <template #actions>
            <button type="button" @click="openCreateModal" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> New Payroll Run
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search payroll runs" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="runColumns"
                    :data-source="runs.data" :pagination="false" :loading="runs.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (runs.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'period'">
                            {{ record.period_label }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span :class="statusBadge(record.status)">{{ record.status_label }}</span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="runs.meta" />
            </div>
        </div>
    </div>

    <VModal :show-modal="showCreateModal" @close-click="showCreateModal = false" title="Create Payroll Run">
        <template #modal-body>
            <form @submit.prevent="storeRun" class="row g-3">
                <div v-if="!currentFiscalYear" class="col-12">
                    <div class="alert alert-warning mb-0 py-2">
                        No current fiscal year set. Please ask the super admin to set one.
                    </div>
                </div>
                <template v-else>
                    <div class="col-12">
                        <label class="form-label">Payroll Period</label>
                    </div>
                    <div class="col-7">
                        <select v-model="cForm.bs_month" class="form-select">
                            <option v-for="(name, idx) in bsMonthNames" :key="idx" :value="idx + 1">{{ name }}</option>
                        </select>
                    </div>
                    <div class="col-5">
                        <div class="form-control bg-light text-muted">
                            {{ cForm.bs_year ?? '—' }} BS
                        </div>
                    </div>
                </template>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="btn btn-cancel">Cancel</button>
                    <VButton :loading="cSubmitting" :disabled="!currentFiscalYear" />
                </div>
            </form>
        </template>
    </VModal>

    <VModal :show-modal="showPayModal" @close-click="showPayModal = false" title="Confirm Payroll as Paid">
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
                    <div v-if="payError" class="text-danger small mt-1">{{ payError }}</div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="showPayModal = false" class="btn btn-cancel">Cancel</button>
                    <button type="button" @click="confirmPay" :disabled="isPaying" class="btn btn-success">
                        <span v-if="isPaying" class="spinner-border spinner-border-sm me-1"></span>
                        Confirm &amp; Post to Ledger
                    </button>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { apiAdmin } from '@/helpers/api.js';
import { runColumns, createRowActions } from './runsTableConfig.js';

const router = useRouter();
const payrollStore = usePayrollStore();
const { runs } = storeToRefs(payrollStore);

const showCreateModal = ref(false);
const cSubmitting = ref(false);
const fiscalYears = ref([]);

const showPayModal = ref(false);
const payRunId = ref(null);
const paidAccountId = ref('');
const payError = ref('');
const isPaying = ref(false);
const accountList = ref([]);

const bsMonthNames = ['Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin', 'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'];

const cForm = reactive({ fiscal_year_id: null, bs_month: 1, bs_year: null });

// The current BS year/month, resolved from the server's Nepali calendar.
const currentBsPeriod = ref(null);

const currentFiscalYear = computed(() => fiscalYears.value.find(fy => fy.is_current) ?? null);

const statusBadge = (s) => ({
    draft:           'badge bg-secondary',
    pending_approval:'badge bg-warning',
    processed:       'badge bg-primary',
    paid:            'badge bg-success',
}[s?.value ?? s] ?? 'badge bg-secondary');

const fetchRuns = () => payrollStore.getRuns(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 10 },
    onFilter: fetchRuns,
});

onMounted(async () => {
    try {
        const [fyRes, periodRes, accRes] = await Promise.all([
            apiAdmin('admin-setting/fiscal-year'),
            apiAdmin('hr/payroll/current-period'),
            apiAdmin('account?limit=200'),
        ]);
        fiscalYears.value = fyRes.data.data;
        currentBsPeriod.value = periodRes.data.data;
        cForm.bs_year = currentBsPeriod.value.bs_year;
        cForm.bs_month = currentBsPeriod.value.bs_month;
        accountList.value = accRes.data.data ?? [];
    } catch (e) {
        showErrors(e);
    }
});

const openCreateModal = () => {
    if (currentFiscalYear.value) {
        cForm.fiscal_year_id = currentFiscalYear.value.id;
        if (currentBsPeriod.value) {
            cForm.bs_year = currentBsPeriod.value.bs_year;
            cForm.bs_month = currentBsPeriod.value.bs_month;
        }
    }
    showCreateModal.value = true;
};

const storeRun = async () => {
    if (!currentFiscalYear.value) return;
    cForm.fiscal_year_id = currentFiscalYear.value.id;
    cSubmitting.value = true;
    try {
        const res = await payrollStore.storeRun(cForm);
        toast(res.status, res.data.message);
        showCreateModal.value = false;
        fetchRuns();
    } catch (e) {
        showErrors(e);
    } finally {
        cSubmitting.value = false;
    }
};

const processRun = async (id) => {
    try {
        const res = await payrollStore.processRun(id);
        toast(res.status, res.data.message);
    } catch (e) {
        showErrors(e);
    }
};

const { confirmDelete } = useConfirmAction();

const openPayModal = (id) => {
    payRunId.value = id;
    paidAccountId.value = '';
    payError.value = '';
    showPayModal.value = true;
};

const confirmPay = async () => {
    payError.value = '';
    if (!paidAccountId.value) {
        payError.value = 'Please select a payment account.';
        return;
    }
    isPaying.value = true;
    try {
        const res = await payrollStore.confirmRun(payRunId.value, { paid_account_id: paidAccountId.value });
        toast(res.status, res.data.message);
        showPayModal.value = false;
        fetchRuns();
    } catch (e) {
        showErrors(e);
    } finally {
        isPaying.value = false;
    }
};

const rowActions = createRowActions({
    onView:    (id) => router.push({ name: 'admin.hr-payroll-detail', params: { id } }),
    onProcess: processRun,
    onConfirm: openPayModal,
    onDelete:  (id) => confirmDelete(
        () => payrollStore.deleteRun(id),
        fetchRuns,
    ),
});
</script>
