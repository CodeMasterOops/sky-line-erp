<template>
    <PageHeader title="Damage Reports" subtitle="Record and manage stock damage write-offs" @refresh="fetchReports">
        <template #actions>
            <button
                v-can="'create_damage_report'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>Add Damage Report
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search reference"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="damageReportColumns"
                    :data-source="reports.data"
                    :pagination="false"
                    :loading="reports.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (reports.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="reports.meta" />
            </div>
        </div>
    </div>

    <CreateDamageReport v-model:create-modal-opened="createModalOpened" />
    <EditDamageReport v-model:report_id="editReportId" />
</template>

<script setup>
import {computed, ref} from 'vue';
import {storeToRefs} from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateDamageReport from './Create.vue';
import EditDamageReport from './Edit.vue';
import {useDamageReportStore} from '@/stores/admin/inventory/damage-report.js';
import {useUrlFilter} from '@/composables/useUrlFilter.js';
import {useTablePagination} from '@/composables/useTablePagination.js';
import {useConfirmAction} from '@/composables/useConfirmAction.js';
import {damageReportColumns, createRowActions} from './tableConfig.js';

const damageReportStore = useDamageReportStore();
const {reports} = storeToRefs(damageReportStore);

const createModalOpened = ref(false);
const editReportId = ref('');

const {confirmAction, confirmDelete} = useConfirmAction();

const {filter, onSearchInput, resetFilters, isFiltered} = useUrlFilter({
    defaults: {search: '', page: 1, limit: 25},
    onFilter: (f) => damageReportStore.getReports({filter: f}),
});

const {handleTableChange} = useTablePagination({
    meta: computed(() => reports.value.meta),
    filter,
});

function fetchReports() {
    damageReportStore.getReports({filter});
}

const rowActions = createRowActions({
    onEdit: (id) => {
        editReportId.value = id;
    },
    onApprove: (id) => confirmAction({
        title: 'Approve Damage Report?',
        text: 'Stock will be written off. This cannot be undone.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => damageReportStore.approveReport(id),
        onSuccess: fetchReports,
    }),
    onDelete: (id) => confirmDelete(
        () => damageReportStore.deleteReport(id),
        fetchReports,
    ),
});
</script>
