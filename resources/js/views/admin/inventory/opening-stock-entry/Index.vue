<template>
    <PageHeader title="Opening Stock" subtitle="Record initial on-hand quantities at go-live" @refresh="fetchEntries">
        <template #actions>
            <button
                v-can="'import_opening_stock'"
                type="button"
                class="btn btn-outline-primary d-flex align-items-center me-2"
                @click="openImportWizard">
                <i class="ti ti-file-import me-2"></i>Import
            </button>
            <button
                v-can="'create_opening_stock_entry'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>Add Opening Stock
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search reference or product"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="openingStockColumns"
                    :data-source="entries.data"
                    :pagination="false"
                    :loading="entries.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (entries.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'products'">
                            <span class="text-truncate d-inline-block" style="max-width: 280px;" :title="record.product_names">
                                {{ record.product_names || '—' }}
                            </span>
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="entries.meta" />
            </div>
        </div>
    </div>

    <CreateOpeningStockEntry v-model:create-modal-opened="createModalOpened" />
    <EditOpeningStockEntry v-model:entry_id="editEntryId" />
    <OpeningStockImportWizard ref="importWizardRef" @imported="fetchEntries" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateOpeningStockEntry from './Create.vue';
import EditOpeningStockEntry from './Edit.vue';
import OpeningStockImportWizard from '@/views/admin/data-transfer/OpeningStockImportWizard.vue';
import { useOpeningStockEntryStore } from '@/stores/admin/inventory/opening-stock-entry.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { openingStockColumns, createRowActions } from './tableConfig.js';

const openingStockEntryStore = useOpeningStockEntryStore();
const { entries } = storeToRefs(openingStockEntryStore);

const createModalOpened = ref(false);
const editEntryId = ref('');
const importWizardRef = ref(null);

function openImportWizard() {
    importWizardRef.value?.show();
}

const { confirmAction, confirmDelete } = useConfirmAction();

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 25 },
    onFilter: (f) => openingStockEntryStore.getEntries({ filter: f }),
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => entries.value.meta),
    filter,
});

function fetchEntries() {
    openingStockEntryStore.getEntries({ filter });
}

const rowActions = createRowActions({
    onEdit: (id) => {
        editEntryId.value = id;
    },
    onApprove: (id) => confirmAction({
        title: 'Approve Opening Stock?',
        text: 'This will post quantities to inventory and cannot be undone from this screen.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => openingStockEntryStore.approveEntry(id),
        onSuccess: fetchEntries,
    }),
    onDelete: (id) => confirmDelete(
        () => openingStockEntryStore.deleteEntry(id),
        fetchEntries,
    ),
});
</script>
