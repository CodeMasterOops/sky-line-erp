<template>
    <PageHeader title="Quotations" subtitle="Manage your quotations" @refresh="fetchQuotations">
        <template #actions>
            <button v-can="'create_quotation'" type="button" @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Quotation
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search quotation" :is-filtered="isFiltered"
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
                <a-table class="table datanew table-hover table-center mb-0" :columns="quotationColumns"
                    :data-source="quotations.data" :pagination="false" :loading="quotations.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (quotations.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="quotations.meta" />
            </div>
        </div>
    </div>

    <CreateQuotation v-model:create-modal-opened="createModalOpened" />
    <CreateSalesOrder
        v-model:create-modal-opened="salesOrderModalOpened"
        v-model:quotation-id="salesOrderQuotationId"
        @created="fetchQuotations"
    />
    <CreateInvoice
        v-model:create-modal-opened="invoiceModalOpened"
        v-model:quotation-id="invoiceQuotationId"
        @created="fetchQuotations"
    />
    <EditQuotation v-model:quotation_id="edit_quotation_id" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateQuotation from './Create.vue';
import CreateSalesOrder from '@/views/admin/sales/sales-order/Create.vue';
import CreateInvoice from '@/views/admin/sales/invoice/Create.vue';
import EditQuotation from './Edit.vue';
import { useQuotationStore } from '@/stores/admin/sales/quotation.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { quotationColumns, createRowActions } from './tableConfig.js';

const quotationStore = useQuotationStore();
const { quotations } = storeToRefs(quotationStore);

const createModalOpened = ref(false);
const salesOrderModalOpened = ref(false);
const edit_quotation_id = ref('');
const invoiceModalOpened = ref(false);
const salesOrderQuotationId = ref('');
const invoiceQuotationId = ref('');

const fetchQuotations = () => quotationStore.getQuotations({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchQuotations,
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
    meta: computed(() => quotations.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const editQuotation = (id) => { edit_quotation_id.value = id; };

const convertToInvoice = (id) => {
    invoiceQuotationId.value = id;
    invoiceModalOpened.value = true;
};

const handleDelete = (id) => {
    confirmDelete(
        () => quotationStore.deleteQuotation(id),
        fetchQuotations,
    );
};

const handleApprove = (id) => {
    confirmAction({
        title: 'Approve Quotation?',
        text: 'This will mark the quotation as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes',
        action: () => quotationStore.approveQuotation(id),
        onSuccess: fetchQuotations,
    });
};

const handleConvertToSale = (id) => {
    salesOrderQuotationId.value = id;
    salesOrderModalOpened.value = true;
};

const rowActions = createRowActions({
    onEdit: editQuotation,
    onConvertSale: handleConvertToSale,
    onConvertInvoice: convertToInvoice,
    onApprove: handleApprove,
    onDelete: handleDelete,
});
</script>
