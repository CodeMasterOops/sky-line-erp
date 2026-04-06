<template>
    <PageHeader title="Bills" subtitle="Manage your bills" @refresh="fetchBills">
        <template #actions>
            <button
                v-can="'create_bill'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Bill
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
                        placeholder="Search bill"
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
                    :data-source="bills.data"
                    :pagination="pagination"
                    :loading="bills.loading"
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
                                        @click="editBill(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'approved'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="recordPayment(record.id)">
                                        <i class="ti ti-receipt"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveBill(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteBill(record.id)">
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

    <CreateBill v-model:create-modal-opened="createModalOpened"/>
    <EditBill v-model:bill_id="edit_bill_id"/>
    <PaymentModal v-model:open="paymentModalOpened" v-model:bill-id="paymentBillId" @saved="fetchBills"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateBill from './Create.vue';
import EditBill from './Edit.vue';
import PaymentModal from '@/views/admin/purchase/payment/PaymentModal.vue';
import {useBillStore} from '@/stores/admin/purchase/bill.js';

const billStore = useBillStore();

const {bills} = storeToRefs(billStore);

const createModalOpened = ref(false);
const edit_bill_id = ref('');
const paymentModalOpened = ref(false);
const paymentBillId = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: bills.value.meta.total || 0,
    current: bills.value.meta.current_page || 1,
    pageSize: bills.value.meta.per_page || filter.limit,
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
        title: 'Bill No',
        dataIndex: 'bill_no',
        sorter: true,
    },
    {
        title: 'Bill Date',
        dataIndex: 'bill_date',
        sorter: true,
    },
    {
        title: 'Due Date',
        dataIndex: 'due_date',
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
    fetchBills();
});

const fetchBills = () => {
    billStore.getBills({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchBills();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchBills();
};

const editBill = (id) => {
    edit_bill_id.value = id;
};

const deleteBill = async (id) => {
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
                let res = await billStore.deleteBill(id);
                toast(res.status, res.data.message);
                fetchBills();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveBill = async (id) => {
    Swal.fire({
        title: 'Approve Bill?',
        text: 'This will mark the bill as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await billStore.approveBill(id);
                toast(res.status, res.data.message);
                fetchBills();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const recordPayment = (id) => {
    paymentBillId.value = id;
    paymentModalOpened.value = true;
};
</script>
