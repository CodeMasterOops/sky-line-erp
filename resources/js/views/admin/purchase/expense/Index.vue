<template>
    <PageHeader title="Expenses" subtitle="Manage your expenses" @refresh="fetchExpenses">
        <template #actions>
            <button
                v-can="'create_expense'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Expense
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
                        placeholder="Search expense"
                        @input="debouncedFetch"
                    >
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div style="min-width: 220px;">
                    <VMultiselect
                        id="filter_party_id"
                        v-model="filter.party_id"
                        :options="parties.data"
                        label="Supplier"
                    />
                </div>
                <div style="min-width: 180px;">
                    <VDatepicker
                        id="date_from"
                        v-model="filter.date_from"
                        label="From"
                    />
                </div>
                <div style="min-width: 180px;">
                    <VDatepicker
                        id="date_to"
                        v-model="filter.date_to"
                        label="To"
                    />
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="expenses.data"
                    :pagination="pagination"
                    :loading="expenses.loading"
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
                                        @click="editExpense(record.id)">
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
                                        @click="approveExpense(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteExpense(record.id)">
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

    <CreateExpense v-model:create-modal-opened="createModalOpened"/>
    <EditExpense v-model:expense_id="edit_expense_id"/>
    <PaymentModal
        v-model:open="paymentModalOpened"
        v-model:payable-id="paymentExpenseId"
        :payable-type="'expense'"
        :lock-payable-type="true"
        @saved="fetchExpenses"
    />
</template>

<script setup>
import {computed, onMounted, reactive, ref, watch} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateExpense from './Create.vue';
import EditExpense from './Edit.vue';
import PaymentModal from '@/views/admin/purchase/payment/PaymentModal.vue';
import {useExpenseStore} from '@/stores/admin/purchase/expense.js';
import {usePartyStore} from '@/stores/admin/party.js';

const expenseStore = useExpenseStore();
const partyStore = usePartyStore();

const {expenses} = storeToRefs(expenseStore);
const {parties} = storeToRefs(partyStore);

const createModalOpened = ref(false);
const edit_expense_id = ref('');
const paymentModalOpened = ref(false);
const paymentExpenseId = ref('');

const filter = reactive({
    search: '',
    party_id: '',
    date_from: '',
    date_to: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: expenses.value.meta.total || 0,
    current: expenses.value.meta.current_page || 1,
    pageSize: expenses.value.meta.per_page || filter.limit,
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
        title: 'Expense No',
        dataIndex: 'expense_no',
        sorter: true,
    },
    {
        title: 'Reference No',
        dataIndex: 'reference_no',
        sorter: true,
    },
    {
        title: 'Date',
        dataIndex: 'date',
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
    fetchExpenses();
    partyStore.getParties({filter: {type: 'supplier'}});
});

const fetchExpenses = () => {
    expenseStore.getExpenses({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchExpenses();
}, 300);

watch(() => [filter.party_id, filter.date_from, filter.date_to], () => {
    filter.page = 1;
    fetchExpenses();
});

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchExpenses();
};

const editExpense = (id) => {
    edit_expense_id.value = id;
};

const recordPayment = (id) => {
    paymentExpenseId.value = id;
    paymentModalOpened.value = true;
};

const deleteExpense = async (id) => {
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
                let res = await expenseStore.deleteExpense(id);
                toast(res.status, res.data.message);
                fetchExpenses();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveExpense = async (id) => {
    Swal.fire({
        title: 'Approve Expense?',
        text: 'This will mark the expense as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await expenseStore.approveExpense(id);
                toast(res.status, res.data.message);
                fetchExpenses();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
