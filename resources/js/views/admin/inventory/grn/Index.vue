<template>
    <PageHeader title="Goods Received Notes" subtitle="Manage stock receipts from suppliers" @refresh="fetchGrns">
        <template #actions>
            <button
                v-can="'create_grn'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>Create GRN
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search GRN No..."
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters">
            <template #filters>
                <select v-model="filter.status" class="form-select form-select-sm w-auto">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                </select>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="grnColumns"
                    :data-source="tableData"
                    :pagination="false"
                    :loading="grns.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (grns.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'grn_no'">
                            <router-link
                                :to="{ name: 'admin.grn-view', params: { id: record.id } }"
                                class="text-primary fw-semibold">
                                {{ record.grn_no }}
                            </router-link>
                        </template>
                        <template v-else-if="column.key === 'received_date'">
                            {{ formatDate(record.received_date) }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'billing_status'">
                            <span class="badge bg-info-subtle text-info text-capitalize">
                                {{ billingLabel(record.billing_status) }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="grns.meta" />
            </div>
        </div>
    </div>

    <CreateGrn v-model:create-modal-opened="createModalOpened" @saved="fetchGrns" />
    <EditGrn v-model:grn-id="editGrnId" @saved="fetchGrns" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateGrn from './Create.vue';
import EditGrn from './Edit.vue';
import { useGrnStore } from '@/stores/admin/inventory/grn.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { formatDate } from '@/helpers/helper.js';
import { grnColumns, createRowActions } from './tableConfig.js';

const router = useRouter();
const grnStore = useGrnStore();
const { grns } = storeToRefs(grnStore);
const { confirmDelete, confirmAction } = useConfirmAction();

const createModalOpened = ref(false);
const editGrnId = ref('');

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 25 },
    onFilter: (f) => grnStore.getGrns({ filter: f }),
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => grns.value.meta),
    filter,
});

const tableData = computed(() =>
    (grns.value.data || []).map((grn) => ({
        ...grn,
        party_name: grn.party?.name || '-',
        warehouse_name: grn.warehouse?.name || '-',
    })),
);

const billingLabel = (status) => (status || 'open').replace(/_/g, ' ');

function fetchGrns() {
    grnStore.getGrns({ filter });
}

const rowActions = createRowActions({
    onView: (id) => router.push({ name: 'admin.grn-view', params: { id } }),
    onEdit: (id) => { editGrnId.value = String(id); },
    onApprove: (id) => confirmAction({
        title: 'Approve GRN?',
        text: 'This will receive stock into the warehouse.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => grnStore.approveGrn(id),
        onSuccess: fetchGrns,
    }),
    onDelete: (id) => confirmDelete(
        () => grnStore.deleteGrn(id),
        fetchGrns,
    ),
});
</script>
