<template>
    <PageHeader title="Purchase Orders" subtitle="Manage your purchase orders" @refresh="fetchOrders">
        <template #actions>
            <button v-can="'create_purchase_order'" type="button" @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add New
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search purchase order" :is-filtered="isFiltered"
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
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="purchaseOrderColumns"
                    :data-source="orders.data" :pagination="false" :loading="orders.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (orders.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge"
                                :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
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

    <CreatePurchaseOrder v-model:create-modal-opened="createModalOpened" />
    <EditPurchaseOrder v-model:order_id="edit_order_id" />
    <PurchaseOrderDetailModal v-model:detail-order-id="detail_order_id" />
    <CreateBill v-model:create-modal-opened="invoiceModalOpened" v-model:purchase-order-id="invoiceReferenceId" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreatePurchaseOrder from './Create.vue';
import EditPurchaseOrder from './Edit.vue';
import PurchaseOrderDetailModal from './DetailModal.vue';
import CreateBill from '@/views/admin/purchase/bill/Create.vue';
import { usePurchaseOrderStore } from '@/stores/admin/purchase/purchase-order.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { purchaseOrderColumns, createRowActions } from './tableConfig.js';

const purchaseOrderStore = usePurchaseOrderStore();
const { orders } = storeToRefs(purchaseOrderStore);

const createModalOpened = ref(false);
const edit_order_id = ref('');
const detail_order_id = ref('');
const invoiceModalOpened = ref(false);
const invoiceReferenceId = ref('');

const fetchOrders = () => purchaseOrderStore.getOrders({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchOrders,
});

const selectedStatus = computed(() => {
    const s = filter.status;
    if (!s) return '';
    const labels = { draft: 'Draft', approved: 'Approved' };
    return labels[s] ?? s;
});

function setFilter(key, value) {
    filter[key] = value;
}

const { handleTableChange } = useTablePagination({
    meta: computed(() => orders.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const editOrder = (id) => { edit_order_id.value = id; };

const openDetail = (id) => { detail_order_id.value = String(id); };

const convertToBill = (id) => {
    invoiceReferenceId.value = id;
    invoiceModalOpened.value = true;
};

const handleDelete = (id) => {
    confirmDelete(
        () => purchaseOrderStore.deleteOrder(id),
        fetchOrders,
    );
};

const handleApprove = (id) => {
    confirmAction({
        title: 'Approve Purchase Order?',
        text: 'This will mark the order as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes',
        action: () => purchaseOrderStore.approveOrder(id),
        onSuccess: fetchOrders,
    });
};

const rowActions = createRowActions({
    onView: openDetail,
    onEdit: editOrder,
    onApprove: handleApprove,
    onConvertToBill: convertToBill,
    onDelete: handleDelete,
});
</script>
