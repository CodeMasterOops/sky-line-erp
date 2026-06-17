<template>
    <PageHeader title="Designations" subtitle="Manage job designations" @refresh="fetchDesignations">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search designations" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="designationColumns"
                    :data-source="designations.data" :pagination="false" :loading="designations.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (designations.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="designations.meta" />
            </div>
        </div>
    </div>

    <CreateDesignation v-model:create-modal-opened="createModalOpened" />
    <EditDesignation v-model:edit-id="editId" />
</template>

<script setup>
import { ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import { useDesignationStore } from '@/stores/admin/hr/designation.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import CreateDesignation from './Create.vue';
import EditDesignation from './Edit.vue';
import { designationColumns, createRowActions } from './tableConfig.js';

const store = useDesignationStore();
const { designations } = storeToRefs(store);
const createModalOpened = ref(false);
const editId = ref('');

const fetchDesignations = () => store.getDesignations(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 10 },
    onFilter: fetchDesignations,
});

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onEdit: (id) => { editId.value = id; },
    onDelete: (id) => confirmDelete(
        () => store.deleteDesignation(id),
        fetchDesignations,
    ),
});
</script>
