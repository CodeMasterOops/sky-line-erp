<template>
    <PageHeader title="Credit Notes" subtitle="Manage your credit notes" @refresh="fetchCreditNotes">
        <template #actions>
            <button
                v-can="'create_credit_note'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Credit Note
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <a href="javascript:void(0);" class="btn-searchset">
                        <i class="ti ti-search fs-14 feather-search"></i>
                    </a>
                    <input
                        type="search"
                        v-model="filter.search"
                        class="form-control form-control-sm"
                        placeholder="Search credit note"
                        @input="debouncedFetch"
                    >
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="creditNotes.data"
                    :pagination="pagination"
                    :loading="creditNotes.loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ index + 1 }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span
                                class="badge"
                                :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        title="View"
                                        @click="openDetail(record.id)">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 edit-icon p-2"
                                        href="javascript:void(0);"
                                        @click="editCreditNote(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveCreditNote(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteCreditNote(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
            </div>
        </div>
    </div>

    <CreateCreditNote v-model:create-modal-opened="createModalOpened"/>
    <EditCreditNote v-model:credit_note_id="edit_credit_note_id"/>
    <CreditNoteDetailModal v-model:detail-credit-note-id="detail_credit_note_id"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateCreditNote from './Create.vue';
import EditCreditNote from './Edit.vue';
import CreditNoteDetailModal from './DetailModal.vue';
import {useCreditNoteStore} from '@/stores/admin/sales/credit-note.js';

const creditNoteStore = useCreditNoteStore();

const {creditNotes} = storeToRefs(creditNoteStore);

const createModalOpened = ref(false);
const edit_credit_note_id = ref('');
const detail_credit_note_id = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: creditNotes.value.meta.total || 0,
    current: creditNotes.value.meta.current_page || 1,
    pageSize: creditNotes.value.meta.per_page || filter.limit,
    showSizeChanger: true,
    showQuickJumper: true,
}));

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Credit Note No',
        dataIndex: 'credit_note_no',
        sorter: true,
    },
    {
        title: 'Credit Note Date',
        dataIndex: 'credit_note_date',
        sorter: true,
    },
    {
        title: 'Customer',
        dataIndex: 'party_name',
        sorter: true,
    },
    {
        title: 'Grand Total',
        dataIndex: 'grand_total',
        sorter: true,
    },
    {
        title: 'Status',
        key: 'status',
    },
    {
        title: 'Action',
        key: 'action',
    },
];

onMounted(() => {
    fetchCreditNotes();
});

const fetchCreditNotes = () => {
    creditNoteStore.getCreditNotes({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchCreditNotes();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchCreditNotes();
};

const editCreditNote = (id) => {
    edit_credit_note_id.value = id;
};

const openDetail = (id) => {
    detail_credit_note_id.value = id;
};

const deleteCreditNote = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await creditNoteStore.deleteCreditNote(id);
                toast(res.status, res.data.message);
                fetchCreditNotes();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveCreditNote = async (id) => {
    Swal.fire({
        title: 'Approve Credit Note?',
        text: 'This will mark the credit note as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await creditNoteStore.approveCreditNote(id);
                toast(res.status, res.data.message);
                fetchCreditNotes();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
