<template>
    <PageHeader title="Debit Notes" subtitle="Manage your debit notes" @refresh="fetchDebitNotes">
        <template #actions>
            <button
                v-can="'create_debit_note'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Debit Note
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
                        placeholder="Search debit note"
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
                    :data-source="debitNotes.data"
                    :pagination="pagination"
                    :loading="debitNotes.loading"
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
                                        v-if="record.status === 'draft'"
                                        class="me-2 edit-icon p-2"
                                        href="javascript:void(0);"
                                        @click="editDebitNote(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveDebitNote(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteDebitNote(record.id)">
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

    <CreateDebitNote v-model:create-modal-opened="createModalOpened"/>
    <EditDebitNote v-model:debit_note_id="edit_debit_note_id"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateDebitNote from './Create.vue';
import EditDebitNote from './Edit.vue';
import {useDebitNoteStore} from '@/stores/admin/purchase/debit-note.js';

const debitNoteStore = useDebitNoteStore();

const {debitNotes} = storeToRefs(debitNoteStore);

const createModalOpened = ref(false);
const edit_debit_note_id = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: debitNotes.value.meta.total || 0,
    current: debitNotes.value.meta.current_page || 1,
    pageSize: debitNotes.value.meta.per_page || filter.limit,
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
        title: 'Debit Note No',
        dataIndex: 'debit_note_no',
        sorter: true,
    },
    {
        title: 'Debit Note Date',
        dataIndex: 'debit_note_date',
        sorter: true,
    },
    {
        title: 'Supplier',
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
    fetchDebitNotes();
});

const fetchDebitNotes = () => {
    debitNoteStore.getDebitNotes({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchDebitNotes();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchDebitNotes();
};

const editDebitNote = (id) => {
    edit_debit_note_id.value = id;
};

const deleteDebitNote = async (id) => {
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
                let res = await debitNoteStore.deleteDebitNote(id);
                toast(res.status, res.data.message);
                fetchDebitNotes();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveDebitNote = async (id) => {
    Swal.fire({
        title: 'Approve Debit Note?',
        text: 'This will mark the debit note as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await debitNoteStore.approveDebitNote(id);
                toast(res.status, res.data.message);
                fetchDebitNotes();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
