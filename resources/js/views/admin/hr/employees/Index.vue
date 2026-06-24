<template>
    <PageHeader title="Employees" subtitle="Manage your workforce" @refresh="fetchEmployees">
        <template #actions>
            <router-link :to="{ name: 'admin.hr-employee-create' }" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Employee
            </router-link>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search by name, code, email..." :is-filtered="isFiltered"
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
                                @click="filter.status = 'active'">Active</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.status = 'inactive'">Inactive</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.status = 'terminated'">Terminated</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="employeeColumns"
                    :data-source="employees.data" :pagination="false" :loading="employees.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (employees.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'department'">
                            {{ record.department?.name || '—' }}
                        </template>
                        <template v-else-if="column.key === 'designation'">
                            {{ record.designation?.name || '—' }}
                        </template>
                        <template v-else-if="column.key === 'type'">
                            {{ record.employment_type_label }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span :class="statusBadge(record.status)">{{ record.status_label }}</span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="employees.meta" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { employeeColumns, createRowActions } from './tableConfig.js';

const router = useRouter();
const store = useEmployeeStore();
const { employees } = storeToRefs(store);

const fetchEmployees = () => store.getEmployees(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchEmployees,
});

const selectedStatus = computed(() => {
    const labels = { active: 'Active', inactive: 'Inactive', terminated: 'Terminated' };
    return labels[filter.status] ?? '';
});

const statusBadge = (status) => ({
    active: 'badge bg-success',
    inactive: 'badge bg-secondary',
    terminated: 'badge bg-danger',
}[status?.value ?? status] ?? 'badge bg-secondary');

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onLedger: (id) => router.push({ name: 'admin.hr-employee-salary-ledger', params: { id } }),
    onEdit: (id) => router.push({ name: 'admin.hr-employee-edit', params: { id } }),
    onDelete: (id) => confirmDelete(
        () => store.deleteEmployee(id),
        fetchEmployees,
    ),
});
</script>
