<template>
    <PageHeader title="Opening Stock" subtitle="Record initial on-hand quantities at go-live" @refresh="fetchEntries">
        <template #actions>
            <button
                v-can="'create_opening_stock_entry'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Opening Stock
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <a href="javascript:void(0);" class="btn-searchset">
                        <i class="ti ti-search fs-14 feather-search"></i>
                    </a>
                    <input
                        type="search"
                        v-model="filter.search"
                        class="form-control form-control-sm"
                        placeholder="Search reference or product"
                        @input="debouncedFetch"
                    >
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="entries.data"
                    :pagination="false"
                    :loading="entries.loading"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (entries.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'products'">
                            <span class="text-truncate d-inline-block" style="max-width: 280px;" :title="record.product_names">
                                {{ record.product_names || '—' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span
                                class="badge"
                                :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a
                                        v-if="record.status === 'draft'"
                                        v-can="'edit_opening_stock_entry'"
                                        class="me-2 edit-icon p-2"
                                        href="javascript:void(0);"
                                        @click="editEntry(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        v-can="'approve_opening_stock_entry'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveEntry(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a
                                        v-can="'delete_opening_stock_entry'"
                                        data-bs-toggle="modal"
                                        class="p-2"
                                        href="javascript:void(0);"
                                        @click="deleteEntry(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="entries.meta" />
            </div>
        </div>
    </div>

    <CreateOpeningStockEntry v-model:create-modal-opened="createModalOpened"/>
    <EditOpeningStockEntry v-model:entry_id="edit_entry_id"/>
</template>

<script setup>
import {onMounted, reactive, ref, watch} from 'vue';
import VPagination from '@/components/base/VPagination.vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateOpeningStockEntry from './Create.vue';
import EditOpeningStockEntry from './Edit.vue';
import {useOpeningStockEntryStore} from '@/stores/admin/inventory/opening-stock-entry.js';

const openingStockEntryStore = useOpeningStockEntryStore();
const {entries} = storeToRefs(openingStockEntryStore);

const createModalOpened = ref(false);
const edit_entry_id = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const columns = [
    {title: 'SN', key: 'sn', width: 60},
    {title: 'Reference', dataIndex: 'reference_no', sorter: true},
    {title: 'Products', key: 'products'},
    {title: 'Date', dataIndex: 'date', sorter: true},
    {title: 'Warehouse', dataIndex: 'warehouse', sorter: true},
    {title: 'Status', key: 'status'},
    {title: 'Action', key: 'action'},
];

onMounted(() => {
    fetchEntries();
});

const fetchEntries = () => {
    openingStockEntryStore.getEntries({filter});
};

const debouncedFetch = debounce(() => {
    const onFirstPage = filter.page === 1;
    filter.page = 1;
    if (onFirstPage) {
        fetchEntries();
    }
}, 300);

watch(() => [filter.page, filter.limit], () => {
    fetchEntries();
});

const editEntry = (id) => {
    edit_entry_id.value = id;
};

const deleteEntry = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete?',
        text: 'If you delete this draft entry, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                const res = await openingStockEntryStore.deleteEntry(id);
                toast(res.status, res.data.message);
                fetchEntries();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveEntry = async (id) => {
    Swal.fire({
        title: 'Approve Opening Stock?',
        text: 'This will post quantities to inventory and cannot be undone from this screen.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                const res = await openingStockEntryStore.approveEntry(id);
                toast(res.status, res.data.message);
                fetchEntries();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
