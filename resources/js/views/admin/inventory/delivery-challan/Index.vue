<template>
    <PageHeader title="Delivery Challans" subtitle="Manage goods delivery notes" @refresh="fetchChallans">
        <template #actions>
            <button
                v-can="'create_delivery_challan'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>Create Challan
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search challan no"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters">
            <template #filters>
                <div style="min-width: 140px;">
                    <VMultiselect
                        id="filter_status"
                        v-model="filter.status"
                        :options="statusOptions"
                        placeholder="All Status"
                        size="sm"
                    />
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="deliveryChallanColumns"
                    :data-source="tableData"
                    :pagination="false"
                    :loading="challans.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (challans.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'challan_no'">
                            <router-link
                                :to="{ name: 'admin.delivery-challan-view', params: { id: record.id } }"
                                class="text-primary fw-semibold">
                                {{ record.challan_no }}
                            </router-link>
                        </template>
                        <template v-else-if="column.key === 'challan_date'">
                            {{ formatDate(record.challan_date) }}
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="challans.meta" />
            </div>
        </div>
    </div>

    <CreateDeliveryChallan v-model:create-modal-opened="createModalOpened" @saved="fetchChallans" />
    <EditDeliveryChallan v-model:challan-id="editChallanId" @saved="fetchChallans" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateDeliveryChallan from './Create.vue';
import EditDeliveryChallan from './Edit.vue';
import { useDeliveryChallanStore } from '@/stores/admin/inventory/delivery-challan.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { formatDate } from '@/helpers/helper.js';
import { deliveryChallanColumns, createRowActions } from './tableConfig.js';

const router = useRouter();
const deliveryChallanStore = useDeliveryChallanStore();
const { challans } = storeToRefs(deliveryChallanStore);
const { confirmDelete, confirmAction } = useConfirmAction();

const createModalOpened = ref(false);
const editChallanId = ref('');

const statusOptions = [
    { id: 'draft', name: 'Draft' },
    { id: 'approved', name: 'Approved' },
];

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 25 },
    onFilter: (f) => deliveryChallanStore.getChallans({ filter: f }),
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => challans.value.meta),
    filter,
});

const tableData = computed(() =>
    (challans.value.data || []).map((challan) => ({
        ...challan,
        party_name: challan.party?.name || '-',
        warehouse_name: challan.warehouse?.name || '-',
    })),
);

function fetchChallans() {
    deliveryChallanStore.getChallans({ filter });
}

const rowActions = createRowActions({
    onView: (id) => router.push({ name: 'admin.delivery-challan-view', params: { id } }),
    onEdit: (id) => { editChallanId.value = String(id); },
    onApprove: (id) => confirmAction({
        title: 'Approve Delivery Challan?',
        text: 'This will issue stock from the warehouse.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => deliveryChallanStore.approveChallan(id),
        onSuccess: fetchChallans,
    }),
    onDelete: (id) => confirmDelete(
        () => deliveryChallanStore.deleteChallan(id),
        fetchChallans,
    ),
});
</script>
