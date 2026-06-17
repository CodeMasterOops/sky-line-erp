<template>
    <PageHeader title="Departments" subtitle="Manage company departments" @refresh="fetchDepartments">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search departments" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="departmentColumns"
                    :data-source="departments.data" :pagination="false" :loading="departments.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (departments.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                {{ record.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="departments.meta" />
            </div>
        </div>
    </div>

    <CreateDepartment v-model:create-modal-opened="createModalOpened" />
    <EditDepartment v-model:edit-id="editId" />
</template>

<script setup>
import { ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import { useDepartmentStore } from '@/stores/admin/hr/department.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import CreateDepartment from './Create.vue';
import EditDepartment from './Edit.vue';
import { departmentColumns, createRowActions } from './tableConfig.js';

const departmentStore = useDepartmentStore();
const { departments } = storeToRefs(departmentStore);
const createModalOpened = ref(false);
const editId = ref('');

const fetchDepartments = () => departmentStore.getDepartments(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 10 },
    onFilter: fetchDepartments,
});

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onEdit: (id) => { editId.value = id; },
    onDelete: (id) => confirmDelete(
        () => departmentStore.deleteDepartment(id),
        fetchDepartments,
    ),
});
</script>
