<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'vendor.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Manage Stock</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Stock List</h5>
      </div>
      <div class="card-body">
        <VDataTable :meta="products.meta" v-model:filter="filter">
          <table class="table table-bordered">
            <thead>
            <tr class="align-middle">
              <th>SN</th>
              <th>Product Name</th>
              <th>Variant</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="products.loading" :colspan="7"/>
            <template v-else-if="products.data.length">
              <template v-for="(product,index) in products.data" :key="index">
                <tr v-for="(variant,key) in product.variants" :key="variant.id">
                  <th v-if="key===0" :rowspan="product.variants.length">
                    {{ products.meta.from + index }}
                  </th>
                  <td v-if="key===0" :rowspan="product.variants.length">
                    <a href="#" class="d-flex align-items-center gap-2">
                      <img v-if="product.thumbnail_image_url" :src="product.thumbnail_image_url" height="50" width="50" class="border p-1" :alt="product.name">
                      <div style="max-width: 300px;">
                        {{ product.name }}
                      </div>
                    </a>
                  </td>
                  <td>
                    <span v-for="option in variant.variant_options" :key="option.id" class="badge bg-info mx-1">
                      {{ option.value }}
                    </span>
                    {{!variant.variant_options.length ? 'No Variant' : '' }}
                  </td>
                  <td>
                    €
                    {{ variant.sales_price }}
                    <del
                        v-if="variant.price!==variant.sales_price && variant.price>0">
                      {{ variant.price }}
                    </del>
                  </td>
                  <td>
                    {{variant.available_quantity}}
                  </td>
                  <td v-if="key===0" :rowspan="product.variants.length">
                    {{ product.is_active ? 'Active' : 'InActive'}}
                  </td>
                  <td style="width: 90px;">
                    <button
                        type="button"
                        @click.prevent="selectedVariant={
                          variant_id:variant.id,
                          product:product,
                          variant:variant
                        }"
                        title="Edit Stock"
                        class="btn btn-xs btn-outline-warning">
                      <i class="fa fa-edit"> </i>
                    </button>
                    <router-link
                        :to="{name:'vendor.stock-history',params:{id:variant.id}}"
                        type="button"
                        title="Stock History"
                        class="btn btn-xs btn-outline-primary">
                      <i class="fa fa-history"> </i>
                    </router-link>
                  </td>
                </tr>
              </template>
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
  <EditStock v-model:variant_id="selectedVariant.variant_id" :product="selectedVariant.product" :variant="selectedVariant.variant" @after-submit="fetchProducts"/>
</template>

<script setup>
import {onMounted, reactive, ref, watch} from "vue";
import {storeToRefs} from "pinia";
import EditStock from "./Edit.vue";
import {useVendorStockStore} from "@/stores/vendor/vendor-stock.js";

const stockStore = useVendorStockStore();

onMounted(() => {
  fetchProducts();
})

const filter = reactive({})

watch(() => filter, () => {
  fetchProducts();
}, {deep: true})

const fetchProducts = () => {
  stockStore.getStockList({filter});
}

const {stocks:products} = storeToRefs(stockStore);

const selectedVariant=ref({
  variant_id:'',
  product:{},
  variant:{},
})

</script>
