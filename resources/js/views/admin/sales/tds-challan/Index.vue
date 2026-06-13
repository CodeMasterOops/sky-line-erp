<template>
    <PageHeader title="TDS Challans" subtitle="Manage TDS challans for IRD submission" @refresh="fetchChallans">
        <template #actions>
            <button type="button" class="btn btn-primary d-flex align-items-center" @click="showCreateModal = true">
                <i class="ti ti-circle-plus me-2"></i>
                New Challan
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search challan"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters">
            <template #filters>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ selectedStatus || 'Status' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('status', '')">All</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('status', 'pending')">Pending</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('status', 'submitted')">Submitted</a></li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="challans.data"
                    :pagination="false"
                    :loading="challans.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (challans.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'total_tds_amount'">
                            {{ formatMoney(record.total_tds_amount) }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="record.status === 'submitted' ? 'bg-success' : 'bg-warning text-dark'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="d-flex gap-1">
                                <router-link
                                    :to="{ name: 'admin.tds-challan-view', params: { id: record.id } }"
                                    class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-eye"></i>
                                </router-link>
                                <button
                                    v-if="record.status === 'pending'"
                                    type="button"
                                    class="btn btn-sm btn-outline-success"
                                    @click="handleMarkSubmitted(record.id)">
                                    <i class="ti ti-check"></i> Submit
                                </button>
                            </div>
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="challans.meta" />
            </div>
        </div>
    </div>

    <CreateChallanModal v-model:open="showCreateModal" @created="fetchChallans" />
</template>

<script setup>
import {computed, ref} from 'vue';
import {storeToRefs} from 'pinia';
import {formatMoney} from '@/helpers/formatMoney.js';
import {toast} from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import {useTdsChallanStore} from '@/stores/admin/sales/tds-challan.js';
import {useUrlFilter} from '@/composables/useUrlFilter.js';
import {useTablePagination} from '@/composables/useTablePagination.js';
import {useConfirmAction} from '@/composables/useConfirmAction.js';
import CreateChallanModal from './CreateChallanModal.vue';

const challanStore = useTdsChallanStore();
const {challans} = storeToRefs(challanStore);

const showCreateModal = ref(false);

const fetchChallans = () => challanStore.getChallans({filter});

const {filter, onSearchInput, resetFilters, isFiltered} = useUrlFilter({
    defaults: {search: '', status: '', page: 1, limit: 10},
    onFilter: fetchChallans,
});

const selectedStatus = computed(() => {
    const labels = {pending: 'Pending', submitted: 'Submitted'};
    return labels[filter.status] ?? '';
});

function setFilter(key, value) {
    filter[key] = value;
}

const {handleTableChange} = useTablePagination({
    meta: computed(() => challans.value.meta),
    filter,
});

const {confirmAction} = useConfirmAction();

const handleMarkSubmitted = (id) => {
    confirmAction({
        title: 'Mark as Submitted?',
        text: 'This will mark the TDS challan as submitted to IRD.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Submit',
        action: () => challanStore.markSubmitted(id),
        onSuccess: () => toast('success', 'Challan marked as submitted.'),
    });
};

const columns = [
    {title: 'SN', key: 'sn', width: 60},
    {title: 'Challan No', dataIndex: 'challan_no'},
    {title: 'Date', dataIndex: 'challan_date', sorter: true},
    {title: 'Party', dataIndex: 'party_name'},
    {title: 'Period Month', dataIndex: 'period_month'},
    {title: 'Total TDS', key: 'total_tds_amount'},
    {title: 'Status', key: 'status'},
    {title: 'Action', key: 'action'},
];
</script>
