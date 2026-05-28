<template>
    <PageHeader title="Data Transfer" subtitle="Import and export history" @refresh="fetchJobs">
        <template #actions>
            <router-link
                v-can="'import_product'"
                :to="{ name: 'admin.product-list' }"
                class="btn btn-primary"
            >
                Import products
            </router-link>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search filename" @search="resetPageAndFetch" @reset="resetFilters">
            <template #filters>
                <select v-model="filter.direction" class="form-select form-select-sm me-2" @change="resetPageAndFetch">
                    <option value="">All directions</option>
                    <option value="import">Import</option>
                    <option value="export">Export</option>
                </select>
                <select v-model="filter.status" class="form-select form-select-sm" @change="resetPageAndFetch">
                    <option value="">All statuses</option>
                    <option value="completed">Completed</option>
                    <option value="completed_with_errors">Completed with errors</option>
                    <option value="processing">Processing</option>
                    <option value="failed">Failed</option>
                </select>
            </template>
        </VTableToolbar>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Entity</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!jobs.data.length && !jobs.loading">
                            <td colspan="7" class="text-center text-muted py-4">No transfer jobs yet.</td>
                        </tr>
                        <tr v-for="job in jobs.data" :key="job.uuid">
                            <td class="text-capitalize">{{ job.direction }}</td>
                            <td class="text-capitalize">{{ job.entity_type?.replace('_', ' ') }}</td>
                            <td>{{ job.original_filename || '—' }}</td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark">{{ job.status }}</span>
                            </td>
                            <td class="small">
                                <span v-if="job.stats?.processed != null">
                                    {{ job.stats.processed }} / {{ job.stats.total_rows || job.stats.valid || '—' }}
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="small">{{ formatDate(job.created_at) }}</td>
                            <td>
                                <button
                                    v-if="job.can_download"
                                    type="button"
                                    class="btn btn-sm btn-outline-primary me-1"
                                    @click="download(job)"
                                >
                                    Download
                                </button>
                                <button
                                    v-if="job.has_errors_download"
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="downloadErrors(job)"
                                >
                                    Errors
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <VPagination v-if="jobs.meta?.last_page > 1" :meta="jobs.meta" @page-change="onPageChange" />
    </div>
</template>

<script setup>
import { onMounted, reactive } from 'vue';
import { storeToRefs } from 'pinia';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import { formatDate } from '@/helpers/helper.js';

const store = useDataTransferStore();
const { jobs } = storeToRefs(store);

const filter = reactive({
    page: 1,
    limit: 25,
    search: '',
    direction: '',
    status: '',
});

const { fetch, resetPageAndFetch } = usePaginatedList({
    fetchFn: () => store.getJobs({ filter }),
    defaults: filter,
});

const fetchJobs = fetch;

onMounted(fetch);

const resetFilters = () => {
    filter.search = '';
    filter.direction = '';
    filter.status = '';
    resetPageAndFetch();
};

const onPageChange = (page) => {
    filter.page = page;
};

const download = (job) => {
    const ext = job.direction === 'export' ? 'csv' : 'csv';
    store.downloadResult(job.uuid, `${job.entity_type}-export.${ext}`);
};

const downloadErrors = (job) => store.downloadErrors(job.uuid);
</script>
