<template>
    <PageHeader title="Sales Orders" subtitle="Manage your sales orders" @refresh="fetchOrders">
        <template #actions>
            <button v-can="'create_sales_order'" type="button" @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Sales Order
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search order" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <select v-model="filter.status" class="form-select form-select-sm" style="min-width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                </select>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="salesOrderColumns"
                    :data-source="orders.data" :pagination="false" :loading="orders.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (orders.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="orders.meta" />
            </div>
        </div>
    </div>

    <CreateSalesOrder v-model:create-modal-opened="createModalOpened" />
    <EditSalesOrder v-model:order_id="edit_order_id" />
    <SalesOrderDetailModal v-model:detail-order-id="detail_order_id" />
    <CreateInvoiceFromReference v-model:open="invoiceModalOpened" v-model:reference-id="invoiceReferenceId"
        v-model:reference-type="invoiceReferenceType" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateSalesOrder from './Create.vue';
import EditSalesOrder from './Edit.vue';
import SalesOrderDetailModal from './DetailModal.vue';
import CreateInvoiceFromReference from '@/views/admin/sales/invoice/CreateFromReference.vue';
import { useSalesOrderStore } from '@/stores/admin/sales/sales-order.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { salesOrderColumns, createRowActions } from './tableConfig.js';

const salesOrderStore = useSalesOrderStore();
const { orders } = storeToRefs(salesOrderStore);

const createModalOpened = ref(false);
const edit_order_id = ref('');
const detail_order_id = ref('');
const invoiceModalOpened = ref(false);
const invoiceReferenceId = ref('');
const invoiceReferenceType = ref('');

const fetchOrders = () => salesOrderStore.getOrders({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchOrders,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => orders.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const editOrder = (id) => { edit_order_id.value = id; };

const openDetail = (id) => { detail_order_id.value = String(id); };

const handleDelete = (id) => {
    confirmDelete(
        () => salesOrderStore.deleteOrder(id),
        fetchOrders,
    );
};

const handleApprove = (id) => {
    confirmAction({
        title: 'Approve Sales Order?',
        text: 'This will mark the order as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => salesOrderStore.approveOrder(id),
        onSuccess: fetchOrders,
    });
};

const convertToInvoice = (id) => {
    invoiceReferenceId.value = id;
    invoiceReferenceType.value = 'sales-order';
    invoiceModalOpened.value = true;
};

const rowActions = createRowActions({
    onView:    openDetail,
    onEdit:    editOrder,
    onApprove: handleApprove,
    onConvert: convertToInvoice,
    onDelete:  handleDelete,
});
</script>
