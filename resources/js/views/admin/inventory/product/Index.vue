<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'admin.dashboard'}">
                    <i class="fa fa-home"> Home</i>
                </router-link>
            </li>
            <li class="breadcrumb-item active">Products</li>
        </ol>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">Product List</h5>
                <button
                    v-can="'create_product'"
                    type="button"
                    @click.prevent="createModalOpened=true"
                    class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-plus-circle"> Add New</i>
                </button>
            </div>
            <div class="card-body">
                <VDataTable :meta="products.meta" v-model:filter="filter">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>SN</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Sales Price</th>
                            <th>Purchase Price</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="align-middle">
                        <VLoader v-if="products.loading" :colspan="6" />
                        <template v-else-if="products.data.length">
                            <tr v-for="(product,index) in products.data" :key="index">
                                <th>{{ products.meta.from + index }}</th>
                                <td>
                                    {{ product.name }}
                                </td>
                                <td>
                                    {{ product.code }}
                                </td>
                                <td>
                                    {{ product.sales_price }}
                                </td>
                                <td>
                                    {{ product.purchase_price }}
                                </td>
                                <td style="width:90px;">
                                    <button
                                        v-can="'edit_product'"
                                        type="button"
                                        @click.prevent="edit_product_id=product.id"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit"> </i>
                                    </button>
                                    <button v-can="'delete_product'" @click="deleteProduct(product.id)" type="button"
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
                </VDataTable>
            </div>
        </div>
    </section>
    <CreateProduct v-model:create-modal-opened="createModalOpened" />
    <EditProduct v-model:product_id="edit_product_id" />
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import CreateProduct from './Create.vue';
import EditProduct from './Edit.vue';
import { useProductStore } from '@/stores/admin/inventory/product.js';

const productStore = useProductStore();

onMounted(() => {
    fetchProducts();
});

const edit_product_id = ref('');
const createModalOpened = ref(false);

const { products } = storeToRefs(productStore);

const filter = reactive({
    product_category_id: '',
    brand_id: ''
});

watch(() => filter, () => {
    fetchProducts();
}, { deep: true });

const fetchProducts = () => {
    productStore.getProducts({ filter });
};

const deleteProduct = async (id) => {
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
                let res = await productStore.deleteProduct(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
