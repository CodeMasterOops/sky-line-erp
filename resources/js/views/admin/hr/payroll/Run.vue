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
                <div v-if="currentFiscalYear" class="col-12">
                    <label class="form-label">Month</label>
                    <select v-model="cForm.month" class="form-select">
                        <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="btn btn-cancel">Cancel</button>
                    <VButton :loading="cSubmitting" :disabled="!currentFiscalYear" />
                </div>
            </form>
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

const now = new Date();
const cForm = reactive({ fiscal_year_id: null, month: now.getMonth() + 1 });

const currentFiscalYear = computed(() => fiscalYears.value.find(fy => fy.is_current) ?? null);

const monthName = (m) => new Date(2000, m - 1, 1).toLocaleString('default', { month: 'long' });
const statusBadge = (s) => ({
    draft:     'badge bg-secondary',
    processed: 'badge bg-primary',
    paid:      'badge bg-success',
}[s?.value ?? s] ?? 'badge bg-secondary');

const fetchRuns = () => payrollStore.getRuns(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 10 },
    onFilter: fetchRuns,
});

onMounted(async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year');
        fiscalYears.value = res.data.data;
    } catch (e) {
        showErrors(e);
    }
});

const openCreateModal = () => {
    if (currentFiscalYear.value) {
        cForm.fiscal_year_id = currentFiscalYear.value.id;
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

const { confirmAction, confirmDelete } = useConfirmAction();

const confirmRun = (id) => confirmAction({
    title:              'Mark payroll as Paid?',
    icon:               'question',
    confirmButtonColor: 'green',
    confirmButtonText:  'Yes, Confirm',
    action:             () => payrollStore.confirmRun(id),
    onSuccess:          fetchRuns,
});

const rowActions = createRowActions({
    onView:    (id) => router.push({ name: 'admin.hr-payroll-detail', params: { id } }),
    onProcess: processRun,
    onConfirm: confirmRun,
    onDelete:  (id) => confirmDelete(
        () => payrollStore.deleteRun(id),
        fetchRuns,
    ),
});
</script>
