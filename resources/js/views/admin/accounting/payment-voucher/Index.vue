<template>
    <PageHeader title="Payment Vouchers" subtitle="Manage your payment vouchers" @refresh="fetchVouchers">
        <template #actions>
            <button
                v-can="'create_payment_voucher'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add New
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
                        placeholder="Search reference"
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
                    :data-source="vouchers.data"
                    :pagination="pagination"
                    :loading="vouchers.loading"
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
                                        @click="editVoucher(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveVoucher(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a class="p-2" href="javascript:void(0);"
                                       @click="deleteVoucher(record.id)">
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

    <CreatePaymentVoucher v-model:create-modal-opened="createModalOpened"/>
    <EditPaymentVoucher v-model:voucher_id="edit_voucher_id"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreatePaymentVoucher from './Create.vue';
import EditPaymentVoucher from './Edit.vue';
import {usePaymentVoucherStore} from '@/stores/admin/accounting/payment-voucher.js';

const paymentVoucherStore = usePaymentVoucherStore();

const {vouchers} = storeToRefs(paymentVoucherStore);

const createModalOpened = ref(false);
const edit_voucher_id = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: vouchers.value.meta.total || 0,
    current: vouchers.value.meta.current_page || 1,
    pageSize: vouchers.value.meta.per_page || filter.limit,
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
        title: 'Date',
        dataIndex: 'date',
        sorter: true,
    },
    {
        title: 'Entry no',
        dataIndex: 'voucher_no',
        sorter: true,
    },
    {
        title: 'Reference',
        dataIndex: 'reference_no',
        sorter: true,
    },
    {
        title: 'Remarks',
        dataIndex: 'remarks',
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
    fetchVouchers();
});

const fetchVouchers = () => {
    paymentVoucherStore.getVouchers({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchVouchers();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchVouchers();
};

const editVoucher = (id) => {
    edit_voucher_id.value = id;
};

const deleteVoucher = async (id) => {
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
                let res = await paymentVoucherStore.deleteVoucher(id);
                toast(res.status, res.data.message);
                fetchVouchers();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveVoucher = async (id) => {
    Swal.fire({
        title: 'Approve Payment Voucher?',
        text: 'This will mark the voucher as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await paymentVoucherStore.approveVoucher(id);
                toast(res.status, res.data.message);
                fetchVouchers();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
