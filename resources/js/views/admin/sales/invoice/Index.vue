<template>
    <PageHeader title="Invoices" subtitle="Manage your invoices" @refresh="fetchInvoices">
        <template #actions>
            <button
                v-can="'create_invoice'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Invoice
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
                        placeholder="Search invoice"
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
                    :data-source="invoices.data"
                    :pagination="pagination"
                    :loading="invoices.loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ index + 1 }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                <span
                                    class="badge"
                                    :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                    {{ record.status }}
                                </span>
                                <span v-if="record.voided_at" class="badge bg-dark">voided</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <router-link
                                        v-can="'show_invoice'"
                                        class="me-2 p-2 text-dark"
                                        :to="{ name: 'admin.invoice-view', params: { id: record.id } }">
                                        <i class="ti ti-eye"></i>
                                    </router-link>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 edit-icon p-2"
                                        href="javascript:void(0);"
                                        @click="editInvoice(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'approved' && !record.voided_at"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="recordPayment(record.id)">
                                        <i class="ti ti-receipt"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveInvoice(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a
                                        v-can="'approve_invoice'"
                                        v-if="record.status === 'approved' && !record.voided_at"
                                        class="me-2 p-2 text-warning"
                                        href="javascript:void(0);"
                                        title="Void"
                                        @click="voidInvoice(record.id)">
                                        <i class="ti ti-ban"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteInvoice(record.id)">
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

    <CreateInvoice v-model:create-modal-opened="createModalOpened"/>
    <EditInvoice v-model:invoice_id="edit_invoice_id"/>
    <ReceiptModal v-model:open="receiptModalOpened" v-model:invoice-id="receiptInvoiceId" @saved="fetchInvoices"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateInvoice from './Create.vue';
import EditInvoice from './Edit.vue';
import ReceiptModal from '@/views/admin/sales/receipt/ReceiptModal.vue';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';

const invoiceStore = useInvoiceStore();

const {invoices} = storeToRefs(invoiceStore);

const createModalOpened = ref(false);
const edit_invoice_id = ref('');
const receiptModalOpened = ref(false);
const receiptInvoiceId = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: invoices.value.meta.total || 0,
    current: invoices.value.meta.current_page || 1,
    pageSize: invoices.value.meta.per_page || filter.limit,
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
        title: 'Invoice No',
        dataIndex: 'invoice_no',
        sorter: true,
    },
    {
        title: 'Invoice Date',
        dataIndex: 'invoice_date',
        sorter: true,
    },
    {
        title: 'Due Date',
        dataIndex: 'due_date',
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
    fetchInvoices();
});

const fetchInvoices = () => {
    invoiceStore.getInvoices({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchInvoices();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchInvoices();
};

const editInvoice = (id) => {
    edit_invoice_id.value = id;
};

const deleteInvoice = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                let res = await invoiceStore.deleteInvoice(id);
                toast(res.status, res.data?.message ?? 'Invoice deleted successfully.');
                fetchInvoices();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveInvoice = async (id) => {
    Swal.fire({
        title: 'Approve Invoice?',
        text: 'This will mark the invoice as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                let res = await invoiceStore.approveInvoice(id);
                toast(res.status, res.data?.message ?? 'Invoice approved successfully.');
                fetchInvoices();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const recordPayment = (id) => {
    receiptInvoiceId.value = id;
    receiptModalOpened.value = true;
};

const voidInvoice = async (id) => {
    Swal.fire({
        title: 'Void invoice?',
        text: 'This reverses inventory and marks the invoice void. It cannot be undone from the UI.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await invoiceStore.voidInvoice(id);
                toast(res.status, res.data?.message ?? 'Invoice voided.');
                fetchInvoices();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
