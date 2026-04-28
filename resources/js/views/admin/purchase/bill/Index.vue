<template>
    <PageHeader title="Bills" subtitle="Manage your bills" @refresh="fetchBills">
        <template #actions>
            <button v-can="'create_bill'" type="button" @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Bill
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search bill" :is-filtered="isFiltered"
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
                <a-table class="table datanew table-hover table-center mb-0" :columns="billColumns"
                    :data-source="bills.data" :pagination="false" :loading="bills.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (bills.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="bills.meta" />
            </div>
        </div>
    </div>

    <CreateBill v-model:create-modal-opened="createModalOpened" />
    <EditBill v-model:bill_id="edit_bill_id" />
    <BillDetailModal v-model:detail-bill-id="detail_bill_id" @voided="fetchBills" />
    <PaymentModal v-model:open="paymentModalOpened" v-model:payable-id="paymentBillId" :payable-type="'bill'"
        :lock-payable-type="true" @saved="fetchBills" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateBill from './Create.vue';
import EditBill from './Edit.vue';
import BillDetailModal from './DetailModal.vue';
import PaymentModal from '@/views/admin/purchase/payment/PaymentModal.vue';
import { useBillStore } from '@/stores/admin/purchase/bill.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { hasPermission } from '@/helpers/checkPermission.js';
import { billColumns, createRowActions } from './tableConfig.js';

const billStore = useBillStore();
const { bills } = storeToRefs(billStore);

const createModalOpened = ref(false);
const edit_bill_id = ref('');
const detail_bill_id = ref('');
const paymentModalOpened = ref(false);
const paymentBillId = ref('');

const fetchBills = () => billStore.getBills({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchBills,
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
    meta: computed(() => bills.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const editBill = (id) => { edit_bill_id.value = id; };

const openDetail = (id) => { detail_bill_id.value = String(id); };

const recordPayment = (id) => {
    paymentBillId.value = id;
    paymentModalOpened.value = true;
};

const handleDelete = (id) => {
    confirmDelete(
        () => billStore.deleteBill(id),
        fetchBills,
    );
};

const approveBillAction = (id) => {
    confirmAction({
        title: 'Approve Bill?',
        text: 'This will mark the bill as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes',
        action: () => billStore.approveBill(id),
        onSuccess: fetchBills,
    });
};

const voidBillAction = (id) => {
    confirmAction({
        title: 'Void bill?',
        text: 'This reverses inventory and marks the bill void.',
        icon: 'warning',
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
        action: () => billStore.voidBill(id),
        onSuccess: fetchBills,
    });
};

const rowActions = createRowActions({
    onView: openDetail,
    onEdit: editBill,
    onRecordPayment: recordPayment,
    onApprove: approveBillAction,
    onVoid: voidBillAction,
    onDelete: handleDelete,
    canViewBill: () => hasPermission('show_bill'),
    canVoidBill: () => hasPermission('approve_bill'),
});
</script>
