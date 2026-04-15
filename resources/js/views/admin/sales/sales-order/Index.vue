<template>
    <PageHeader title="Sales Orders" subtitle="Manage your sales orders" @refresh="fetchOrders">
        <template #actions>
            <button
                v-can="'create_sales_order'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Sales Order
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
                        placeholder="Search order"
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
                    :data-source="orders.data"
                    :pagination="pagination"
                    :loading="orders.loading"
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
                                        @click="editOrder(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveOrder(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'approved' && !record.invoice_count"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="convertToInvoice(record.id)">
                                        <i class="ti ti-file-invoice"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteOrder(record.id)">
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

    <CreateSalesOrder v-model:create-modal-opened="createModalOpened"/>
    <EditSalesOrder v-model:order_id="edit_order_id"/>
    <SalesOrderDetailModal v-model:detail-order-id="detail_order_id"/>
    <CreateInvoiceFromReference
        v-model:open="invoiceModalOpened"
        v-model:reference-id="invoiceReferenceId"
        v-model:reference-type="invoiceReferenceType"
    />
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateSalesOrder from './Create.vue';
import EditSalesOrder from './Edit.vue';
import SalesOrderDetailModal from './DetailModal.vue';
import CreateInvoiceFromReference from '@/views/admin/sales/invoice/CreateFromReference.vue';
import {useSalesOrderStore} from '@/stores/admin/sales/sales-order.js';

const salesOrderStore = useSalesOrderStore();
const {orders} = storeToRefs(salesOrderStore);

const createModalOpened = ref(false);
const edit_order_id = ref('');
const detail_order_id = ref('');
const invoiceModalOpened = ref(false);
const invoiceReferenceId = ref('');
const invoiceReferenceType = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: orders.value.meta.total || 0,
    current: orders.value.meta.current_page || 1,
    pageSize: orders.value.meta.per_page || filter.limit,
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
        title: 'Order No',
        dataIndex: 'order_no',
        sorter: true,
    },
    {
        title: 'Date',
        dataIndex: 'order_date',
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
    fetchOrders();
});

const fetchOrders = () => {
    salesOrderStore.getOrders({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchOrders();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchOrders();
};

const editOrder = (id) => {
    edit_order_id.value = id;
};

const openDetail = (id) => {
    detail_order_id.value = String(id);
};

const deleteOrder = async (id) => {
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
                let res = await salesOrderStore.deleteOrder(id);
                toast(res.status, res.data.message);
                fetchOrders();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveOrder = async (id) => {
    Swal.fire({
        title: 'Approve Sales Order?',
        text: 'This will mark the order as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await salesOrderStore.approveOrder(id);
                toast(res.status, res.data.message);
                fetchOrders();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const convertToInvoice = (id) => {
    invoiceReferenceId.value = id;
    invoiceReferenceType.value = 'sales-order';
    invoiceModalOpened.value = true;
};
</script>
