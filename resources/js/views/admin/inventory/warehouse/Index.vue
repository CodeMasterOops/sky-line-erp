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
                        :data-source="warehouses.data"
                        :loading="warehouses.loading"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ index + 1 }}
                            </template>
                            <template v-else-if="column.key === 'parent'">
                                {{ record.parent?.name || '—' }}
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
import {onMounted, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import CreateWarehouse from './Create.vue';
import EditWarehouse from './Edit.vue';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';

const warehouseStore = useWarehouseStore();

onMounted(() => {
    fetchWarehouses();
});

const edit_warehouse_id = ref('');
const createModalOpened = ref(false);

const {warehouses} = storeToRefs(warehouseStore);

const fetchWarehouses = (refetch = false) => {
    warehouseStore.getWarehouses(refetch);
}

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Parent',
        key: 'parent',
    },
    {
        title: 'Name',
        dataIndex: 'name',
        sorter: {
            compare: (a, b) => {
                a = a.name.toLowerCase();
                b = b.name.toLowerCase();
                return a > b ? -1 : b > a ? 1 : 0;
            },
        },
    },
    {
        title: 'Code',
        dataIndex: 'code',
        sorter: {
            compare: (a, b) => {
                a = a.code.toLowerCase();
                b = b.code.toLowerCase();
                return a > b ? -1 : b > a ? 1 : 0;
            },
        },
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
