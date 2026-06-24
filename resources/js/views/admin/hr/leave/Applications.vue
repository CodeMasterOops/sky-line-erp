<template>
    <PageHeader title="Leave Applications" subtitle="Manage employee leave requests" @refresh="fetchApplications">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> New Application
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search applications" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ selectedStatus || 'Status' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.status = ''">All Statuses</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.status = 'pending'">Pending</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.status = 'approved'">Approved</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.status = 'rejected'">Rejected</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="applicationColumns"
                    :data-source="applications.data" :pagination="false" :loading="applications.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (applications.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'employee'">
                            {{ record.employee?.full_name }}
                        </template>
                        <template v-else-if="column.key === 'leave_type'">
                            {{ record.leave_type?.name }}
                        </template>
                        <template v-else-if="column.key === 'from_date'">
                            {{ record.from_date }}
                            <div v-if="record.bs_from_date" class="text-muted small">{{ record.bs_from_date }} BS</div>
                        </template>
                        <template v-else-if="column.key === 'to_date'">
                            {{ record.to_date }}
                            <div v-if="record.bs_to_date" class="text-muted small">{{ record.bs_to_date }} BS</div>
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span :class="statusBadge(record.status)">{{ record.status_label }}</span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="applications.meta" />
            </div>
        </div>
    </div>

    <CreateLeaveApplication v-model:create-modal-opened="createModalOpened" @created="fetchApplications" />
</template>

<script setup>
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import { useLeaveStore } from '@/stores/admin/hr/leave.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import CreateLeaveApplication from './CreateApplication.vue';
import { applicationColumns, createRowActions } from './applicationsTableConfig.js';

const store = useLeaveStore();
const { applications } = storeToRefs(store);
const createModalOpened = ref(false);

const fetchApplications = () => store.getApplications(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: 'pending', page: 1, limit: 10 },
    onFilter: fetchApplications,
});

const selectedStatus = computed(() => {
    const labels = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected' };
    return labels[filter.status] ?? '';
});

const statusBadge = (s) => ({
    pending:  'badge bg-warning text-dark',
    approved: 'badge bg-success',
    rejected: 'badge bg-danger',
}[s?.value ?? s] ?? 'badge bg-secondary');

const approve = async (id) => {
    try {
        const res = await store.approveApplication(id);
        toast(res.status, res.data.message);
        fetchApplications();
    } catch (e) {
        showErrors(e);
    }
};

const reject = async (id) => {
    const { value: reason } = await Swal.fire({
        title: 'Rejection Reason',
        input: 'textarea',
        showCancelButton: true,
    });
    if (reason !== undefined) {
        try {
            const res = await store.rejectApplication(id, reason);
            toast(res.status, res.data.message);
            fetchApplications();
        } catch (e) {
            showErrors(e);
        }
    }
};

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onApprove: approve,
    onReject:  reject,
    onDelete:  (id) => confirmDelete(
        () => store.deleteApplication(id),
        fetchApplications,
    ),
});
</script>
