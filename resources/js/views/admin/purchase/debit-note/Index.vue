<template>
    <PageHeader title="Debit Notes" subtitle="Manage your debit notes" @refresh="fetchDebitNotes">
        <template #actions>
            <button v-can="'create_debit_note'" type="button" @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Debit Note
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search debit note" :is-filtered="isFiltered"
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
                                @click="setFilter('status', '')">All Statuses</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', 'draft')">Draft</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', 'approved')">Approved</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="debitNoteColumns"
                    :data-source="debitNotes.data" :pagination="false" :loading="debitNotes.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (debitNotes.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                <span class="badge"
                                    :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                    {{ record.status }}
                                </span>
                                <span v-if="record.voided_at" class="badge bg-dark">voided</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="debitNotes.meta" />
            </div>
        </div>
    </div>

    <CreateDebitNote v-model:create-modal-opened="createModalOpened" />
    <EditDebitNote v-model:debit_note_id="edit_debit_note_id" />
    <DebitNoteDetailModal v-model:detail-debit-note-id="detail_debit_note_id" @voided="fetchDebitNotes" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateDebitNote from './Create.vue';
import EditDebitNote from './Edit.vue';
import DebitNoteDetailModal from './DetailModal.vue';
import { useDebitNoteStore } from '@/stores/admin/purchase/debit-note.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { hasPermission } from '@/helpers/checkPermission.js';
import { debitNoteColumns, createRowActions } from './tableConfig.js';

const debitNoteStore = useDebitNoteStore();
const { debitNotes } = storeToRefs(debitNoteStore);

const createModalOpened = ref(false);
const edit_debit_note_id = ref('');
const detail_debit_note_id = ref('');

const fetchDebitNotes = () => debitNoteStore.getDebitNotes({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchDebitNotes,
});

const selectedStatus = computed(() => {
    const s = filter.status;
    if (!s) return '';
    const labels = { draft: 'Draft', approved: 'Approved' };
    return labels[s] ?? s;
});

function setFilter(key, value) {
    filter[key] = value;
}

const { handleTableChange } = useTablePagination({
    meta: computed(() => debitNotes.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const editDebitNote = (id) => { edit_debit_note_id.value = id; };

const openDetail = (id) => { detail_debit_note_id.value = String(id); };

const handleDelete = (id) => {
    confirmDelete(
        () => debitNoteStore.deleteDebitNote(id),
        fetchDebitNotes,
    );
};

const approveDebitNoteAction = (id) => {
    confirmAction({
        title: 'Approve Debit Note?',
        text: 'This will mark the debit note as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes',
        action: () => debitNoteStore.approveDebitNote(id),
        onSuccess: fetchDebitNotes,
    });
};

const voidDebitNoteAction = (id) => {
    confirmAction({
        title: 'Void debit note?',
        text: 'This reverses inventory and marks the debit note void.',
        icon: 'warning',
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
        action: () => debitNoteStore.voidDebitNote(id),
        onSuccess: fetchDebitNotes,
    });
};

const rowActions = createRowActions({
    onView: openDetail,
    onEdit: editDebitNote,
    onApprove: approveDebitNoteAction,
    onVoid: voidDebitNoteAction,
    onDelete: handleDelete,
    canViewDebitNote: () => hasPermission('show_debit_note'),
    canVoidDebitNote: () => hasPermission('approve_debit_note'),
});
</script>
