<template>
    <PageHeader title="Quotations" subtitle="Manage your quotations" @refresh="fetchQuotations">
        <template #actions>
            <button
                v-can="'create_quotation'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Quotation
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
                        placeholder="Search quotation"
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
                    :data-source="quotations.data"
                    :pagination="pagination"
                    :loading="quotations.loading"
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
                                        @click="editQuotation(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'approved' && !record.sales_order_count"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="convertToSale(record.id)">
                                        <i class="ti ti-shopping-cart"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'approved' && !record.invoice_count"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="convertToInvoice(record.id)">
                                        <i class="ti ti-file-invoice"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveQuotation(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteQuotation(record.id)">
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

    <CreateQuotation v-model:create-modal-opened="createModalOpened"/>
    <EditQuotation v-model:quotation_id="edit_quotation_id"/>
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
import CreateQuotation from './Create.vue';
import EditQuotation from './Edit.vue';
import CreateInvoiceFromReference from '@/views/admin/accounting/invoice/CreateFromReference.vue';
import {useQuotationStore} from '@/stores/admin/sales/quotation.js';

const quotationStore = useQuotationStore();
const {quotations} = storeToRefs(quotationStore);

const createModalOpened = ref(false);
const edit_quotation_id = ref('');
const invoiceModalOpened = ref(false);
const invoiceReferenceId = ref('');
const invoiceReferenceType = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: quotations.value.meta.total || 0,
    current: quotations.value.meta.current_page || 1,
    pageSize: quotations.value.meta.per_page || filter.limit,
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
        title: 'Quotation No',
        dataIndex: 'quotation_no',
        sorter: true,
    },
    {
        title: 'Date',
        dataIndex: 'quotation_date',
        sorter: true,
    },
    {
        title: 'Customer',
        dataIndex: 'party_name',
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
    fetchQuotations();
});

const fetchQuotations = () => {
    quotationStore.getQuotations({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchQuotations();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchQuotations();
};

const editQuotation = (id) => {
    edit_quotation_id.value = id;
};

const deleteQuotation = async (id) => {
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
                let res = await quotationStore.deleteQuotation(id);
                toast(res.status, res.data.message);
                fetchQuotations();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveQuotation = async (id) => {
    Swal.fire({
        title: 'Approve Quotation?',
        text: 'This will mark the quotation as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await quotationStore.approveQuotation(id);
                toast(res.status, res.data.message);
                fetchQuotations();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const convertToSale = async (id) => {
    Swal.fire({
        title: 'Convert to Sale?',
        text: 'This will create a sales order from the quotation.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await quotationStore.convertToSale(id);
                toast(res.status, res.data.message);
                fetchQuotations();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const convertToInvoice = (id) => {
    invoiceReferenceId.value = id;
    invoiceReferenceType.value = 'quotation';
    invoiceModalOpened.value = true;
};
</script>
