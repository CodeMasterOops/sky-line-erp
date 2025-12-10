<template>
  <div class="product-title">
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
        <router-link
            v-can="'create_product'"
            :to="{name:'admin.product-create'}"
            class="btn btn-sm btn-outline-primary">
          <i class="fa fa-plus-circle"> Add New</i>
        </router-link>
      </div>
      <div class="card-body">
        <VDataTable :meta="products.meta" v-model:filter="filter">
          <table class="table table-bordered">
            <thead>
            <tr class="align-middle">
              <th>SN</th>
              <th>Name</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="products.loading" :colspan="7"/>
            <template v-else-if="products.data.length">
              <tr v-for="(product,index) in products.data" :key="index"
                  :style="product.has_variants ? 'background:#f6f6fb' : ''">
                <th>{{ products.meta.from + index }}</th>
                <td>
                  <a :href="`${siteUrl}/product/${product.slug}`" target="_blank" class="d-flex align-items-center gap-2">
                    <img v-if="product.thumbnail_image_url" :src="product.thumbnail_image_url" height="50" width="50" class="border p-1" :alt="product.name">
                    <div style="max-width: 300px;">
                      {{ product.name }}
                    </div>
                  </a>
                </td>
                <td>
                  <template v-if="product.defaultVariant">
                    €
                    {{ product.defaultVariant.sales_price }}
                    <del
                        v-if="product.defaultVariant.price!==product.defaultVariant.sales_price && product.defaultVariant.price>0">
                      {{ product.defaultVariant.price }}
                    </del>
                  </template>
                  <template v-else>N/A</template>
                </td>
                <td>
                  {{product.stock_quantity}}
                </td>
                <td>
                  <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        @click.prevent="updateStatus(product.id)"
                        type="checkbox"
                        :id="'switch'+index" :checked="product.is_active">
                    <label class="form-check-label" :for="'switch'+index"></label>
                  </div>
                </td>
                <td style="width: 140px;">
                  <router-link
                      v-can="'show_product'"
                      :to="{name:'admin.product-show',params:{id:product.id}}"
                      type="button"
                      title="Edit"
                      class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-eye"> </i>
                  </router-link>
                  <router-link
                      v-can="'edit_product'"
                      :to="{name:'admin.product-edit',params:{id:product.id}}"
                      type="button"
                      title="Edit"
                      class="btn btn-sm btn-outline-warning">
                    <i class="fa fa-edit"> </i>
                  </router-link>
                  <button v-can="'delete_product'"
                          @click="deleteProduct(product.id)"
                          type="button"
                          title="Delete"
                          class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-trash"> </i>
                  </button>
                </td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="7" class="text-center">
                No Result Found.
              </td>
            </tr>
            </tbody>
          </table>
        </VDataTable>
      </div>
    </div>
  </section>
</template>

<script setup>
import {onMounted, reactive, watch} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {useProductStore} from "@/stores/admin/product";
import {storeToRefs} from "pinia";
import {siteUrl} from "@/helpers/helper.js";

const productStore = useProductStore();

onMounted(() => {
  fetchProducts();
})

const filter = reactive({})

watch(() => filter, () => {
  fetchProducts();
}, {deep: true})

const fetchProducts = () => {
  productStore.getProducts({filter});
}

const {products} = storeToRefs(productStore);

const deleteProduct = async (id) => {
  Swal.fire({
    title: 'Are You Sure to Delete ? ',
    text: "If you delete this, it will be gone forever.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: 'red',
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await productStore.deleteProduct(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}

const updateStatus = async (id) => {
  Swal.fire({
    text: "Are you sure you want to change the status?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: "red",
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await productStore.updateStatus(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
