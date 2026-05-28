<template>
    <PageHeader title="Warehouse List" subtitle="Manage your warehouses" @refresh="fetch">
        <template #actions>
            <button
                v-can="'create_warehouse'"
                type="button"
                @click.prevent="createModalOpened=true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="warehouseRows"
                        :loading="warehouses.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (warehouses.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-else-if="column.key === 'name'">
                                <span :style="{ paddingLeft: `${record.depth * 1.25}rem` }">
                                    {{ record.name }}
                                </span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a class="me-2" href="javascript:void(0);"
                                       @click="edit_warehouse_id=record.id">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       @click="deleteWarehouse(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="warehouses.meta" />
                </div>
            </div>
        </div>
    </section>
    <CreateWarehouse v-model:create-modal-opened="createModalOpened"/>
    <EditWarehouse v-model:warehouse_id="edit_warehouse_id"/>
</template>

<script setup>
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateWarehouse from './Create.vue';
import EditWarehouse from './Edit.vue';
import { useWarehouseStore } from '@/stores/admin/inventory/warehouse.js';
import { flattenWarehousesWithOutline } from '@/helpers/warehouseTree.js';

const warehouseStore = useWarehouseStore();
const edit_warehouse_id = ref('');
const createModalOpened = ref(false);
const { warehouses } = storeToRefs(warehouseStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => warehouseStore.getWarehouses({ filter }),
    defaults: { page: 1, limit: 10 },
});

const warehouseRows = computed(() => flattenWarehousesWithOutline(warehouses.value.data));

const columns = [
    { title: 'No.', key: 'sn', width: 72 },
    { title: 'Name', key: 'name' },
    { title: 'Code', dataIndex: 'code' },
    { title: 'Phone', dataIndex: 'phone' },
    { title: 'Address', dataIndex: 'address' },
    { title: 'Action', key: 'action', align: 'center' },
];

const deleteWarehouse = async (id) => {
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
                let res = await warehouseStore.deleteWarehouse(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
