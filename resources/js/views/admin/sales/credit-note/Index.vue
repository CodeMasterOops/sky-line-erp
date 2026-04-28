<template>
    <PageHeader title="Credit Notes" subtitle="Manage your credit notes" @refresh="fetchCreditNotes">
        <template #actions>
            <button v-can="'create_credit_note'" type="button" @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Credit Note
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search credit note" :is-filtered="isFiltered"
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
                <a-table class="table datanew table-hover table-center mb-0" :columns="creditNoteColumns"
                    :data-source="creditNotes.data" :pagination="false" :loading="creditNotes.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (creditNotes.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="creditNotes.meta" />
            </div>
        </div>
    </div>

    <CreateCreditNote v-model:create-modal-opened="createModalOpened" />
    <EditCreditNote v-model:credit_note_id="edit_credit_note_id" />
    <CreditNoteDetailModal v-model:detail-credit-note-id="detail_credit_note_id" @voided="fetchCreditNotes" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateCreditNote from './Create.vue';
import EditCreditNote from './Edit.vue';
import CreditNoteDetailModal from './DetailModal.vue';
import { useCreditNoteStore } from '@/stores/admin/sales/credit-note.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { hasPermission } from '@/helpers/checkPermission.js';
import { creditNoteColumns, createRowActions } from './tableConfig.js';

const creditNoteStore = useCreditNoteStore();
const { creditNotes } = storeToRefs(creditNoteStore);

const createModalOpened = ref(false);
const edit_credit_note_id = ref('');
const detail_credit_note_id = ref('');

const fetchCreditNotes = () => creditNoteStore.getCreditNotes({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchCreditNotes,
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
    meta: computed(() => creditNotes.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const editCreditNote = (id) => { edit_credit_note_id.value = id; };

const openDetail = (id) => { detail_credit_note_id.value = id; };

const handleDelete = (id) => {
    confirmDelete(
        () => creditNoteStore.deleteCreditNote(id),
        fetchCreditNotes,
    );
};

const approveCreditNote = (id) => {
    confirmAction({
        title: 'Approve Credit Note?',
        text: 'This will mark the credit note as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes',
        action: () => creditNoteStore.approveCreditNote(id),
        onSuccess: fetchCreditNotes,
    });
};

const voidCreditNote = (id) => {
    confirmAction({
        title: 'Void credit note?',
        text: 'This reverses return inventory and marks the credit note void.',
        icon: 'warning',
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
        action: () => creditNoteStore.voidCreditNote(id),
        onSuccess: fetchCreditNotes,
    });
};

const rowActions = createRowActions({
    onView: openDetail,
    onEdit: editCreditNote,
    onApprove: approveCreditNote,
    onVoid: voidCreditNote,
    onDelete: handleDelete,
    canViewCreditNote: () => hasPermission('show_credit_note'),
    canVoidCreditNote: () => hasPermission('approve_credit_note'),
});
</script>
