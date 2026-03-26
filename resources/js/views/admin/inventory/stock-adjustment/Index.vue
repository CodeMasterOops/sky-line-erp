<template>
    <PageHeader title="Stock Adjustments" subtitle="Manage your stock adjustments" @refresh="fetchAdjustments">
        <template #actions>
            <button
                v-can="'create_stock_adjustment'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Adjustment
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
                        placeholder="Search reference"
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
                    :data-source="adjustments.data"
                    :pagination="pagination"
                    :loading="adjustments.loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ index + 1 }}
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
                                        class="me-2 edit-icon p-2"
                                        href="javascript:void(0);"
                                        @click="editAdjustment(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="record.status === 'draft'"
                                        class="me-2 p-2"
                                        href="javascript:void(0);"
                                        @click="approveAdjustment(record.id)">
                                        <i class="ti ti-check"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                       @click="deleteAdjustment(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
            </div>
        </div>
    </div>

    <CreateStockAdjustment v-model:create-modal-opened="createModalOpened"/>
    <EditStockAdjustment v-model:adjustment_id="edit_adjustment_id"/>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateStockAdjustment from './Create.vue';
import EditStockAdjustment from './Edit.vue';
import {useStockAdjustmentStore} from '@/stores/admin/inventory/stock-adjustment.js';

const stockAdjustmentStore = useStockAdjustmentStore();

const {adjustments} = storeToRefs(stockAdjustmentStore);

const createModalOpened = ref(false);
const edit_adjustment_id = ref('');

const filter = reactive({
    search: '',
    page: 1,
    limit: 10
});

const pagination = computed(() => ({
    total: adjustments.value.meta.total || 0,
    current: adjustments.value.meta.current_page || 1,
    pageSize: adjustments.value.meta.per_page || filter.limit,
    showSizeChanger: true,
    showQuickJumper: true,
}));

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Reference',
        dataIndex: 'reference_no',
        sorter: true,
    },
    {
        title: 'Date',
        dataIndex: 'date',
        sorter: true,
    },
    {
        title: 'Warehouse',
        dataIndex: 'warehouse',
        sorter: true,
    },
    {
        title: 'Status',
        key: 'status',
    },
    {
        title: 'Action',
        key: 'action',
    },
];

onMounted(() => {
    fetchAdjustments();
});

const fetchAdjustments = () => {
    stockAdjustmentStore.getAdjustments({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchAdjustments();
}, 300);

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchAdjustments();
};

const editAdjustment = (id) => {
    edit_adjustment_id.value = id;
};

const deleteAdjustment = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await stockAdjustmentStore.deleteAdjustment(id);
                toast(res.status, res.data.message);
                fetchAdjustments();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const approveAdjustment = async (id) => {
    Swal.fire({
        title: 'Approve Stock Adjustment?',
        text: 'This will mark the adjustment as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'green',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await stockAdjustmentStore.approveAdjustment(id);
                toast(res.status, res.data.message);
                fetchAdjustments();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
