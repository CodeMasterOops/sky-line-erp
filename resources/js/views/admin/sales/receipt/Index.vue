<template>
    <PageHeader title="Receipts" subtitle="Manage customer receipts" @refresh="fetchReceipts">
        <template #actions>
            <button
                v-can="'create_receipt'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Receipt
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
                        placeholder="Search receipt"
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
                    :data-source="receipts.data"
                    :pagination="pagination"
                    :loading="receipts.loading"
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
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveReceipt(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteReceipt(record.id)">
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

    <ReceiptModal v-model:open="createModalOpened" @saved="fetchReceipts"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import ReceiptModal from './ReceiptModal.vue';
import {useReceiptStore} from '@/stores/admin/sales/receipt.js';

const receiptStore = useReceiptStore();

const {receipts} = storeToRefs(receiptStore);

const createModalOpened = ref(false);

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: receipts.value.meta.total || 0,
    current: receipts.value.meta.current_page || 1,
    pageSize: receipts.value.meta.per_page || filter.limit,
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
        title: 'Receipt No',
        dataIndex: 'receipt_no',
        sorter: true,
    },
    {
        title: 'Date',
        dataIndex: 'receipt_date',
        sorter: true,
    },
    {
        title: 'Customer',
        dataIndex: 'party_name',
        sorter: true,
    },
    {
        title: 'Payment Method',
        dataIndex: 'payment_method',
        sorter: true,
    },
    {
        title: 'Account',
        dataIndex: 'account_name',
        sorter: true,
    },
    {
        title: 'Total Amount',
        dataIndex: 'total_amount',
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
    fetchReceipts();
});

const fetchReceipts = () => {
    receiptStore.getReceipts({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchReceipts();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchReceipts();
};

const deleteReceipt = async (id) => {
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
                let res = await receiptStore.deleteReceipt(id);
                toast(res.status, res.data.message);
                fetchReceipts();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveReceipt = async (id) => {
    Swal.fire({
        title: 'Approve Receipt?',
        text: 'This will mark the receipt as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await receiptStore.approveReceipt(id);
                toast(res.status, res.data.message);
                fetchReceipts();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
