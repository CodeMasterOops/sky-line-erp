<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'admin.dashboard'}">
                    <i class="fa fa-home"> Home</i>
                </router-link>
            </li>
            <li class="breadcrumb-item active">Warehouses</li>
        </ol>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">Warehouse List</h5>
                <button
                    v-can="'create_warehouse'"
                    type="button"
                    @click.prevent="createModalOpened=true"
                    class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-plus-circle"> Add New</i>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>SN</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="align-middle">
                        <VLoader v-if="warehouses.loading" :colspan="6" />
                        <template v-else-if="warehouses.data.length">
                            <tr v-for="(warehouse,index) in warehouses.data" :key="index">
                                <th>{{ index + 1 }}</th>
                                <td>
                                    {{ warehouse.name }}
                                </td>
                                <td>
                                    {{ warehouse.code }}
                                </td>
                                <td>
                                    {{ warehouse.phone || 'N/A' }}
                                </td>
                                <td>
                                    {{ warehouse.address || 'N/A' }}
                                </td>
                                <td style="width:90px;">
                                    <button
                                        v-can="'edit_warehouse'"
                                        type="button"
                                        @click.prevent="edit_warehouse_id=warehouse.id"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit"> </i>
                                    </button>
                                    <button v-can="'delete_warehouse'" @click="deleteWarehouse(warehouse.id)" type="button"
                                            class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-trash"> </i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="6" class="text-center">
                                No Result Found.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <CreateWarehouse v-model:create-modal-opened="createModalOpened" />
    <EditWarehouse v-model:warehouse_id="edit_warehouse_id" />
</template>

<script setup>
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import CreateWarehouse from './Create.vue';
import EditWarehouse from './Edit.vue';
import { useWarehouseStore } from '@/stores/admin/inventory/warehouse.js';

const warehouseStore = useWarehouseStore();

onMounted(() => {
    warehouseStore.getWarehouses();
});

const edit_warehouse_id = ref('');
const createModalOpened = ref(false);

const { warehouses } = storeToRefs(warehouseStore);

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
