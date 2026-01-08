<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'admin.dashboard'}">
                    <i class="fa fa-home"> Home</i>
                </router-link>
            </li>
            <li class="breadcrumb-item active">Product Category</li>
        </ol>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">Category List</h5>
                <button
                    v-can="'create_product_category'"
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
                            <th>Description</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="align-middle">
                        <VLoader v-if="productCategories.loading" :colspan="4" />
                        <template v-else-if="productCategories.data.length">
                            <tr v-for="(category,index) in productCategories.data" :key="index">
                                <th>{{ index + 1 }}</th>
                                <td>
                                    {{ category.name }}
                                </td>
                                <td>
                                    {{ category.description }}
                                </td>
                                <td style="width:90px;">
                                    <button
                                        v-can="'edit_product_category'"
                                        type="button"
                                        @click.prevent="edit_product_category_id=category.id"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit"> </i>
                                    </button>
                                    <button v-can="'delete_product_category'" @click="deleteProductCategory(category.id)" type="button"
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
    <CreateProductCategory v-model:create-modal-opened="createModalOpened" />
    <EditProductCategory v-model:product_category_id="edit_product_category_id" />
</template>

<script setup>
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import CreateProductCategory from './Create.vue';
import EditProductCategory from './Edit.vue';
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';

const categoryStore = useProductCategoryStore();

onMounted(() => {
    categoryStore.getProductCategories();
});

const edit_product_category_id = ref('');
const createModalOpened = ref(false);

const { productCategories } = storeToRefs(categoryStore);

const deleteProductCategory = async (id) => {
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
                let res = await categoryStore.deleteProductCategory(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
