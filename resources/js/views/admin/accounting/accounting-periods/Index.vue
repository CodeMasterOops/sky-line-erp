<template>
    <PageHeader title="Accounting Periods" subtitle="Manage and close accounting periods" @refresh="loadPeriods">
        <template #actions>
            <button
                class="btn btn-outline-primary d-flex align-items-center"
                :disabled="!selectedFiscalYear || generating"
                @click="generatePeriods"
            >
                <span v-if="generating" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="ti ti-refresh me-2"></i>
                Generate Periods
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap">Fiscal Year</label>
                <select
                    v-model="selectedFiscalYear"
                    class="form-select form-select-sm"
                    style="min-width: 280px"
                    :disabled="fiscalYears.loading"
                    @change="loadPeriods"
                >
                    <option value="">— Select Fiscal Year —</option>
                    <option v-for="fy in fiscalYears.data" :key="fy.id" :value="fy.id">
                        {{ fy.year_name }} ({{ formatDate(fy.start_date) }} – {{ formatDate(fy.end_date) }}){{ fy.is_current ? ' · Current' : '' }}
                    </option>
                </select>
            </div>
        </div>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="periods"
                    :pagination="false"
                    :loading="loading"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ index + 1 }}
                        </template>
                        <template v-else-if="column.key === 'start_date'">
                            {{ formatDate(record.start_date) }}
                        </template>
                        <template v-else-if="column.key === 'end_date'">
                            {{ formatDate(record.end_date) }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="statusBadge(record.status)">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'closed_by'">
                            {{ record.closed_by?.name || '—' }}
                        </template>
                        <template v-else-if="column.key === 'closed_at'">
                            {{ record.closed_at ? formatDate(record.closed_at) : '—' }}
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a
                                        v-if="record.status === 'open'"
                                        href="javascript:void(0);"
                                        class="me-2 p-2"
                                        title="Close Period"
                                        @click="handleClose(record)"
                                    >
                                        <i class="ti ti-lock"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'closed'"
                                        href="javascript:void(0);"
                                        class="me-2 p-2"
                                        title="Reopen Period"
                                        @click="handleReopen(record)"
                                    >
                                        <i class="ti ti-lock-open"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'closed'"
                                        href="javascript:void(0);"
                                        class="p-2"
                                        title="Lock Period"
                                        @click="handleLock(record)"
                                    >
                                        <i class="ti ti-shield-lock"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>

                    <template #emptyText>
                        <div class="text-center text-muted py-5">
                            <i class="ti ti-calendar-off display-4 d-block mb-3"></i>
                            {{ selectedFiscalYear ? 'No periods found. Click "Generate Periods" to create them.' : 'Select a fiscal year to view its accounting periods.' }}
                        </div>
                    </template>
                </a-table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import { formatDate } from '@/helpers/helper.js';
import { useAdminSettingStore } from '@/stores/admin/settings/admin-setting.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';

const adminSettingStore = useAdminSettingStore();
const { fiscalYears, currentFiscalYear } = storeToRefs(adminSettingStore);
const { confirmAction } = useConfirmAction();

const selectedFiscalYear = ref('');
const periods = ref([]);
const loading = ref(false);
const generating = ref(false);

const columns = [
    { title: '#', key: 'sn', width: 60 },
    { title: 'Period', dataIndex: 'period_name' },
    { title: 'Start Date', key: 'start_date' },
    { title: 'End Date', key: 'end_date' },
    { title: 'Status', key: 'status' },
    { title: 'Closed By', key: 'closed_by' },
    { title: 'Closed At', key: 'closed_at' },
    { title: 'Actions', key: 'action' },
];

const statusBadge = (status) => ({
    open: 'bg-success',
    closed: 'bg-warning text-dark',
    locked: 'bg-danger',
}[status] ?? 'bg-secondary');

const loadPeriods = async () => {
    if (!selectedFiscalYear.value) {
        periods.value = [];
        return;
    }
    loading.value = true;
    try {
        const res = await apiAdmin('accounting-period', 'get', { fiscal_year_id: selectedFiscalYear.value });
        periods.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const generatePeriods = async () => {
    generating.value = true;
    try {
        const res = await apiAdmin('accounting-period/generate', 'post', { fiscal_year_id: selectedFiscalYear.value });
        toast('success', res.data.message);
        await loadPeriods();
    } catch (e) {
        showErrors(e);
    } finally {
        generating.value = false;
    }
};

const handleClose = (period) => {
    confirmAction({
        title: 'Close Period?',
        text: `This will close "${period.period_name}". No transactions can be posted once closed.`,
        icon: 'warning',
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Close Period',
        action: () => apiAdmin(`accounting-period/${period.id}/close`, 'post'),
        onSuccess: loadPeriods,
    });
};

const handleReopen = (period) => {
    confirmAction({
        title: 'Reopen Period?',
        text: `This will reopen "${period.period_name}" and allow new transactions.`,
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Reopen',
        action: () => apiAdmin(`accounting-period/${period.id}/reopen`, 'post'),
        onSuccess: loadPeriods,
    });
};

const handleLock = (period) => {
    confirmAction({
        title: 'Lock Period?',
        text: `Locking "${period.period_name}" is permanent and cannot be undone.`,
        icon: 'warning',
        confirmButtonColor: 'red',
        confirmButtonText: 'Lock',
        action: () => apiAdmin(`accounting-period/${period.id}/lock`, 'post'),
        onSuccess: loadPeriods,
    });
};

onMounted(async () => {
    await Promise.all([
        adminSettingStore.getFiscalYears(),
        adminSettingStore.getCurrentFiscalYear(),
    ]);

    if (currentFiscalYear.value.data?.id) {
        selectedFiscalYear.value = currentFiscalYear.value.data.id;
        await loadPeriods();
    }
});
</script>
