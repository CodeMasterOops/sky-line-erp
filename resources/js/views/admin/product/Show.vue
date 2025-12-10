<template>
  <div class="product-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Product Detail</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Product Detail</h5>
        <router-link
            :to="{name:'admin.product-list'}"
            class="btn btn-sm btn-outline-primary">
          <i class="fa fa-list"> List</i>
        </router-link>
      </div>
      <div class="card-body">
        <div v-if="productDetail.loading" class="m-5">
          <VLoader loader-type="spinner"/>
        </div>
        <div v-else>
          <div class="row">
            <div class="col-md-5">
              <div class="text-center border">
                <img :src="product.thumbnail_image_url" width="100%" :alt="product.name">
              </div>
              <div v-if="product.images?.length" class="d-flex justify-content-start gap-2 my-2">
                <template v-for="image in product.images" :key="image.id">
                  <img :src="image.image_url" width="90" :alt="image.title">
                </template>
              </div>
            </div>
            <div class="col-md-7">
              <h5 class="">{{ product.name }}</h5>
              <span v-if="product.defaultVariant">
                € {{ product.defaultVariant?.sales_price }}
                <del
                    v-if="product.defaultVariant.price!==product.defaultVariant.sales_price && product.defaultVariant.price>0">
                  € {{ product.defaultVariant.price }}
                </del>
              </span>
              <p class="mb-1">
                <b>Categories : </b>
                <template v-for="(category,index) in product.categories" :key="category.id">
                  {{ category.name }}{{ product.categories.length !== index + 1 ? ', ' : '' }}
                </template>
              </p>
              <p class="mb-1">
                <b>Brand : </b>
                {{ product.brand_name || 'N/A' }}
              </p>
              <p class="mb-1">
                <b>Vendor : </b>
                {{ product.vendor_name || 'N/A' }}
              </p>
              <p class="mb-1">
                <b>Tags : </b>
                <template v-for="(tag,index) in product.tags" :key="tag.id">
                  {{ tag.title }}{{ product.tags.length !== index + 1 ? ', ' : '' }}
                </template>
              </p>
              <template v-if="product.has_variants">
                <div class="table-responsive">
                  <table class="table table-bordered">
                    <thead>
                    <tr>
                      <th>SN</th>
                      <th>Variation</th>
                      <th>SKU</th>
                      <th>Price</th>
                      <th>Stock</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(variant,index) in product.variants" :key="variant.id">
                      <td>{{ index + 1 }}</td>
                      <td>
                        <span v-for="option in variant.variant_options" :key="option.id" class="badge bg-info mx-1">
                          {{ option.attribute_name }} : {{ option.value }}
                        </span>
                      </td>
                      <td>{{ variant.sku }}</td>
                      <td>
                        € {{ variant.sales_price }}
                        <del
                            v-if="variant.price!==variant.sales_price && variant.price>0">
                          {{ variant.price }}
                        </del>
                      </td>
                      <td>{{ variant.stock || 'N/A' }}</td>
                    </tr>
                    </tbody>
                  </table>
                </div>
              </template>
              <template v-else-if="product.defaultVariant">
                <p class="mb-1">
                  <b>SKU : </b>
                  {{ product.defaultVariant.sku || 'N/A' }}
                </p>
                <div class="d-flex justify-content-between">
                  <p class="mb-1">
                    <b>Weight : </b>
                    {{ product.defaultVariant.weight || 'N/A' }}
                  </p>
                  <p class="mb-1">
                    <b>Length : </b>
                    {{ product.defaultVariant.length || 'N/A' }}
                  </p>
                  <p class="mb-1">
                    <b>Width : </b>
                    {{ product.defaultVariant.width || 'N/A' }}
                  </p>
                  <p class="mb-1">
                    <b>Height : </b>
                    {{ product.defaultVariant.height || 'N/A' }}
                  </p>
                </div>
              </template>
              <div v-if="product.attributes?.length" class="table-responsive">
                <h6 class="fw-bold">Attributes</h6>
                <table class="table table-bordered">
                  <thead>
                  <tr>
                    <th>SN</th>
                    <th>Name</th>
                    <th>Values</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(attribute,index) in product.attributes" :key="attribute.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{attribute.name}}</td>
                    <td>
                        <span v-for="attrVal in attribute.values" :key="attrVal.id" class="badge bg-info mx-1">
                          {{ attrVal.value }}
                        </span>
                    </td>
                  </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12 border p-2 m-2">
              <h3>Description</h3>
              <div v-html="product.description"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
<script setup>

import {useRoute} from "vue-router";
import {computed, onMounted} from "vue";
import {useProductStore} from "@/stores/admin/product.js";
import {storeToRefs} from "pinia";

const route = useRoute();
const productStore = useProductStore();

onMounted(() => {
  productStore.getProduct(route.params.id);
})

const {product: productDetail} = storeToRefs(productStore);

const product = computed(() => productDetail.value.data);
</script>
