<template>
    <PageHeader title="Payments" subtitle="Manage supplier payments" @refresh="fetchPayments">
        <template #actions>
            <button
                v-can="'create_payment'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Payment
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
                        placeholder="Search payment"
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
                    :data-source="payments.data"
                    :pagination="pagination"
                    :loading="payments.loading"
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
                                        @click="approvePayment(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deletePayment(record.id)">
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

    <PaymentModal v-model:open="createModalOpened" @saved="fetchPayments"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import PaymentModal from './PaymentModal.vue';
import {usePaymentStore} from '@/stores/admin/purchase/payment.js';

const paymentStore = usePaymentStore();

const {payments} = storeToRefs(paymentStore);

const createModalOpened = ref(false);

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: payments.value.meta.total || 0,
    current: payments.value.meta.current_page || 1,
    pageSize: payments.value.meta.per_page || filter.limit,
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
        title: 'Payment No',
        dataIndex: 'payment_no',
        sorter: true,
    },
    {
        title: 'Date',
        dataIndex: 'payment_date',
        sorter: true,
    },
    {
        title: 'Supplier',
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
    fetchPayments();
});

const fetchPayments = () => {
    paymentStore.getPayments({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchPayments();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchPayments();
};

const deletePayment = async (id) => {
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
                let res = await paymentStore.deletePayment(id);
                toast(res.status, res.data.message);
                fetchPayments();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approvePayment = async (id) => {
    Swal.fire({
        title: 'Approve Payment?',
        text: 'This will mark the payment as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await paymentStore.approvePayment(id);
                toast(res.status, res.data.message);
                fetchPayments();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
