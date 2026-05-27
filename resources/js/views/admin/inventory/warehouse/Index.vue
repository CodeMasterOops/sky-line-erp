<template>
    <PageHeader title="Warehouse List" subtitle="Manage your warehouses" @refresh="fetchWarehouses(true)">
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
                    >
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'sn'">
                                {{ record.outline }}
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
                </div>
            </div>
        </div>
    </section>
    <CreateWarehouse v-model:create-modal-opened="createModalOpened"/>
    <EditWarehouse v-model:warehouse_id="edit_warehouse_id"/>
</template>

<script setup>
import {computed, onMounted, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import CreateWarehouse from './Create.vue';
import EditWarehouse from './Edit.vue';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {flattenWarehousesWithOutline} from './warehouseTree.js';

const warehouseStore = useWarehouseStore();

onMounted(() => {
    fetchWarehouses();
});

const edit_warehouse_id = ref('');
const createModalOpened = ref(false);

const {warehouses} = storeToRefs(warehouseStore);

const warehouseRows = computed(() => flattenWarehousesWithOutline(warehouses.value.data));

const fetchWarehouses = (refetch = false) => {
    warehouseStore.getWarehouses(refetch);
}

const columns = [
    {
        title: 'No.',
        key: 'sn',
        width: 72,
    },
    {
        title: 'Name',
        key: 'name',
    },
    {
        title: 'Code',
        dataIndex: 'code',
    },
    {
        title: 'Phone',
        dataIndex: 'phone',
    },
    {
        title: 'Address',
        dataIndex: 'address',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
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
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
