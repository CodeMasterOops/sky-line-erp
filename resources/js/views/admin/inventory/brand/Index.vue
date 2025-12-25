<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'admin.dashboard'}">
                    <i class="fa fa-home"> Home</i>
                </router-link>
            </li>
            <li class="breadcrumb-item active">Brands</li>
        </ol>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">Brand List</h5>
                <button
                    v-can="'create_brand'"
                    type="button"
                    @click.prevent="createModalOpened=true"
                    class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-plus-circle"> Add New</i>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-responsive table-bordered">
                        <thead>
                        <tr>
                            <th>SN</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="align-middle">
                        <VLoader v-if="brands.loading" :colspan="4" />
                        <template v-else-if="brands.data.length">
                            <tr v-for="(brand,index) in brands.data" :key="index">
                                <th>{{ index + 1 }}</th>
                                <td>
                                    {{ brand.name }}
                                </td>
                                <td>
                                    {{ brand.code }}
                                </td>
                                <td style="width:90px;">
                                    <button
                                        v-can="'edit_brand'"
                                        type="button"
                                        @click.prevent="edit_brand_id=brand.id"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit"> </i>
                                    </button>
                                    <button v-can="'delete_brand'" @click="deleteBrand(brand.id)" type="button"
                                            class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-trash"> </i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="4" class="text-center">
                                No Result Found.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <CreateBrand v-model:create-modal-opened="createModalOpened" />
    <EditBrand v-model:brand_id="edit_brand_id" />
</template>

<script setup>
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import CreateBrand from './Create.vue';
import EditBrand from './Edit.vue';
import { useBrandStore } from '@/stores/admin/inventory/brand.js';

const brandStore = useBrandStore();

onMounted(() => {
    brandStore.getBrands();
});

const edit_brand_id = ref('');
const createModalOpened = ref(false);

const { brands } = storeToRefs(brandStore);

const deleteBrand = async (id) => {
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
                let res = await brandStore.deleteBrand(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
