<template>
    <div>
        <PageHeader title="Data Transfer" subtitle="Import and export history" @refresh="fetch">
            <template #actions>
                <router-link
                    v-can="'import_product'"
                    :to="{ name: 'admin.product-list' }"
                    class="btn btn-primary d-flex align-items-center"
                >
                    <i class="ti ti-upload me-2"></i>
                    Import products
                </router-link>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar />
                <div class="card flex-fill mb-0">
                    <VTableToolbar
                        v-model="filter.search"
                        placeholder="Search filename or type"
                        :is-filtered="isFiltered"
                        @search="onSearchInput"
                        @reset="resetFilters"
                    >
                        <template #filters>
                            <select
                                v-model="filter.direction"
                                class="form-select form-select-sm"
                                style="width: auto; min-width: 130px;"
                                @change="applyFilters"
                            >
                                <option value="">All directions</option>
                                <option value="import">Import</option>
                                <option value="export">Export</option>
                            </select>
                            <select
                                v-model="filter.status"
                                class="form-select form-select-sm"
                                style="width: auto; min-width: 150px;"
                                @change="applyFilters"
                            >
                                <option value="">All statuses</option>
                                <option value="completed">Completed</option>
                                <option value="completed_with_errors">Completed with errors</option>
                                <option value="processing">Processing</option>
                                <option value="validating">Validating</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </template>
                    </VTableToolbar>
                    <div class="card-body">
                        <div class="table-responsive">
                            <a-table
                                class="table datanew table-hover table-center mb-0"
                                :columns="columns"
                                :data-source="jobs.data"
                                :loading="jobs.loading"
                                :pagination="false"
                                row-key="uuid"
                            >
                                <template #bodyCell="{ column, record, index }">
                                    <template v-if="column.key === 'sn'">
                                        {{ rowNumber(index) }}
                                    </template>
                                    <template v-else-if="column.key === 'direction'">
                                        <span class="badge" :class="directionBadgeClass(record.direction)">
                                            {{ record.direction }}
                                        </span>
                                    </template>
                                    <template v-else-if="column.key === 'entity_type'">
                                        <span class="text-capitalize">
                                            {{ formatEntityType(record.entity_type) }}
                                        </span>
                                    </template>
                                    <template v-else-if="column.key === 'status'">
                                        <span class="badge" :class="statusBadgeClass(record.status)">
                                            {{ formatStatus(record.status) }}
                                        </span>
                                    </template>
                                    <template v-else-if="column.key === 'progress'">
                                        <span v-if="record.stats?.processed != null" class="small text-muted">
                                            {{ record.stats.processed }}
                                            /
                                            {{ progressTotal(record) }}
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </template>
                                    <template v-else-if="column.key === 'created_at'">
                                        <span class="small">{{ formatDate(record.created_at) }}</span>
                                    </template>
                                    <template v-else-if="column.key === 'action'">
                                        <div class="action-icon d-inline-flex gap-2">
                                            <a
                                                v-if="record.can_download"
                                                v-can="'list_data_transfer'"
                                                href="javascript:void(0);"
                                                title="Download file"
                                                @click="download(record)"
                                            >
                                                <i class="ti ti-download"></i>
                                            </a>
                                            <a
                                                v-if="record.has_errors_download"
                                                v-can="'list_data_transfer'"
                                                href="javascript:void(0);"
                                                class="text-danger"
                                                title="Download errors"
                                                @click="downloadErrors(record)"
                                            >
                                                <i class="ti ti-file-alert"></i>
                                            </a>
                                        </div>
                                    </template>
                                </template>
                                <template #emptyText>
                                    <span class="text-muted">No transfer jobs yet. Export from a list page or import products to get started.</span>
                                </template>
                            </a-table>
                            <VPagination
                                v-model:page="filter.page"
                                v-model:limit="filter.limit"
                                :meta="jobs.meta"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import debounce from 'lodash/debounce';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import { formatDate } from '@/helpers/helper.js';

const store = useDataTransferStore();
const { jobs } = storeToRefs(store);

const columns = [
    { title: 'SN', key: 'sn', width: 60, align: 'center' },
    { title: 'Type', key: 'direction', width: 100 },
    { title: 'Entity', key: 'entity_type', width: 120 },
    { title: 'File', dataIndex: 'original_filename', ellipsis: true },
    { title: 'Status', key: 'status', width: 140, align: 'center' },
    { title: 'Progress', key: 'progress', width: 110, align: 'center' },
    { title: 'Date', key: 'created_at', width: 130 },
    { title: 'Action', key: 'action', width: 90, align: 'center' },
];

const { filter, fetch, resetPageAndFetch } = usePaginatedList({
    fetchFn: ({ filter: f }) => store.getJobs({ filter: f }),
    defaults: { page: 1, limit: 10, search: '', direction: '', status: '' },
});

const isFiltered = computed(
    () => Boolean(filter.search || filter.direction || filter.status),
);

const rowNumber = (index) =>
    (jobs.value.meta.from || ((filter.page - 1) * filter.limit + 1)) + index;

const applyFilters = () => {
    resetPageAndFetch();
};

const onSearchInput = debounce(() => {
    applyFilters();
}, 300);

const resetFilters = () => {
    filter.search = '';
    filter.direction = '';
    filter.status = '';
    filter.page = 1;
    fetch();
};

const formatEntityType = (type) => (type ? String(type).replace(/_/g, ' ') : '—');

const formatStatus = (status) => (status ? String(status).replace(/_/g, ' ') : '—');

const progressTotal = (record) =>
    record.stats?.total_rows ?? record.stats?.valid ?? '—';

const directionBadgeClass = (direction) =>
    direction === 'export' ? 'bg-info-subtle text-info' : 'bg-primary-subtle text-primary';

const statusBadgeClass = (status) => {
    const map = {
        completed: 'bg-success',
        completed_with_errors: 'bg-warning text-dark',
        processing: 'bg-primary',
        validating: 'bg-info',
        parsed: 'bg-secondary',
        mapped: 'bg-secondary',
        validated: 'bg-secondary',
        uploaded: 'bg-secondary',
        failed: 'bg-danger',
        cancelled: 'bg-dark',
        rolled_back: 'bg-dark',
    };

    return map[status] ?? 'bg-secondary-subtle text-dark';
};

const download = (job) => {
    const name = job.original_filename
        || `${job.entity_type}-${job.direction}.${job.direction === 'export' ? 'csv' : 'csv'}`;
    store.downloadResult(job.uuid, name);
};

const downloadErrors = (job) => store.downloadErrors(job.uuid);
</script>
