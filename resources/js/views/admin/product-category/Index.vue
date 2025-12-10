<template>
  <div class="product-category-title">
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
        <router-link
            v-can="'create_product_category'"
            :to="{name:'admin.product-category-create'}"
            class="btn btn-sm btn-outline-primary">
          <i class="fa fa-plus-circle"> Add New</i>
        </router-link>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead>
            <tr>
              <th>SN</th>
              <th>Name</th>
              <th>Slug</th>
              <th>Featured</th>
              <th>Status</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <VLoader v-if="productCategories.loading" :colspan="5"/>
            <template v-else-if="productCategories.data.length">
              <ProductCategoryRow
                  :categories="productCategories.data"
                  :site-url="siteUrl"
                  @toggle-status="updateStatus"
                  @toggle-featured-status="updateFeaturedStatus"
                  @delete="deleteProductCategory"
              />
            </template>
            <tr v-else>
              <td colspan="5" class="text-center">
                No Result Found.
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import {onMounted} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import {useProductCategoryStore} from "@/stores/admin/product-category.js";
import {siteUrl} from "@/helpers/helper.js";
import ProductCategoryRow from "@/components/ProductCategoryRow.vue";

const categoryStore = useProductCategoryStore();

onMounted(() => {
  fetchCategories();
})

const fetchCategories = (refresh = false) => {
  categoryStore.getProductCategories(refresh);
}

const {productCategories} = storeToRefs(categoryStore);

const deleteProductCategory = async (id) => {
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
        let res = await categoryStore.deleteProductCategory(id);
        fetchCategories(true);
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
        let res = await categoryStore.updateStatus(id);
        fetchCategories(true);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}

const updateFeaturedStatus = async (id) => {
  Swal.fire({
    text: "Are you sure you want to change the featured status?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: "red",
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await categoryStore.updateFeaturedStatus(id);
        fetchCategories(true);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
