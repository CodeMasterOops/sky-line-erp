<template>
    <PageHeader title="Invoices" subtitle="Manage your invoices" @refresh="fetchInvoices">
        <template #actions>
            <button v-can="'create_invoice'" type="button" @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Invoice
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search invoice" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ selectedStatus || 'Status' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', '')">All Statuses</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', 'draft')">Draft</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', 'approved')">Approved</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', 'voided')">Voided</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="invoiceColumns"
                    :data-source="invoices.data" :pagination="false" :loading="invoices.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (invoices.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                <span class="badge"
                                    :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                    {{ record.status }}
                                </span>
                                <span v-if="record.voided_at" class="badge bg-dark">voided</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="invoices.meta" />
            </div>
        </div>
    </div>

    <CreateInvoice v-model:create-modal-opened="createModalOpened" />
    <EditInvoice v-model:invoice_id="edit_invoice_id" />
    <ReceiptModal v-model:open="receiptModalOpened" v-model:invoice-id="receiptInvoiceId" @saved="fetchInvoices" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateInvoice from './Create.vue';
import EditInvoice from './Edit.vue';
import ReceiptModal from '@/views/admin/sales/receipt/ReceiptModal.vue';
import { useInvoiceStore } from '@/stores/admin/sales/invoice.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { hasPermission } from '@/helpers/checkPermission.js';
import { invoiceColumns, createRowActions } from './tableConfig.js';

const router = useRouter();
const invoiceStore = useInvoiceStore();
const { invoices } = storeToRefs(invoiceStore);

const createModalOpened = ref(false);
const edit_invoice_id = ref('');
const receiptModalOpened = ref(false);
const receiptInvoiceId = ref('');

const fetchInvoices = () => invoiceStore.getInvoices({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', date_from: '', date_to: '', page: 1, limit: 10 },
    onFilter: fetchInvoices,
});

const selectedStatus = computed(() => {
    const s = filter.status;
    if (!s) return '';
    const labels = { draft: 'Draft', approved: 'Approved', voided: 'Voided' };
    return labels[s] ?? s;
});

function setFilter(key, value) {
    filter[key] = value;
}

const { handleTableChange } = useTablePagination({
    meta: computed(() => invoices.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const editInvoice = (id) => { edit_invoice_id.value = id; };

const goToInvoiceView = (id) => {
    router.push({ name: 'admin.invoice-view', params: { id } });
};

const recordPayment = (id) => {
    receiptInvoiceId.value = id;
    receiptModalOpened.value = true;
};

const handleDelete = (id) => {
    confirmDelete(
        () => invoiceStore.deleteInvoice(id),
        fetchInvoices,
    );
};

const approveInvoice = (id) => {
    confirmAction({
        title: 'Approve Invoice?',
        text: 'This will mark the invoice as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes',
        action: () => invoiceStore.approveInvoice(id),
        onSuccess: fetchInvoices,
    });
};

const voidInvoice = (id) => {
    confirmAction({
        title: 'Void invoice?',
        text: 'This reverses inventory and marks the invoice void. It cannot be undone from the UI.',
        icon: 'warning',
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
        action: () => invoiceStore.voidInvoice(id),
        onSuccess: fetchInvoices,
    });
};

const rowActions = createRowActions({
    onView: goToInvoiceView,
    onEdit: editInvoice,
    onRecordPayment: recordPayment,
    onApprove: approveInvoice,
    onVoid: voidInvoice,
    onDelete: handleDelete,
    canViewInvoice: () => hasPermission('show_invoice'),
    canVoidInvoice: () => hasPermission('approve_invoice'),
});
</script>
