<template>
  <section class="section dashboard mt-2">
    <div class="row">
      <div class="col-md-3 col-6">
        <div class="info-box">
          <span class="info-box-icon bg-primary elevation-1 text-light">
            <i class="fa fa-sitemap"></i>
          </span>
          <div class="info-box-content">
            <span class="info-box-text">Products</span>
            <span class="info-box-number">{{ dashboard.data.products_count }}</span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="info-box">
          <span class="info-box-icon bg-info elevation-1 text-light">
            <i class="fa fa-file-image-o"></i>
          </span>
          <div class="info-box-content">
            <span class="info-box-text">Media</span>
            <span class="info-box-number">{{ dashboard.data.media_count }}</span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="info-box">
          <span class="info-box-icon bg-primary elevation-1 text-light">
            <i class="fa fa-shopping-cart"></i>
          </span>
          <div class="info-box-content">
            <span class="info-box-text">Orders</span>
            <span class="info-box-number">{{ dashboard.data.orders_count }}</span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="info-box">
          <span class="info-box-icon bg-warning elevation-1 text-light">
            <i class="fa fa-shopping-cart"></i>
          </span>
          <div class="info-box-content">
            <span class="info-box-text">Pending</span>
            <span class="info-box-number">{{ dashboard.data.orders_count }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import {onMounted} from "vue";
import {storeToRefs} from "pinia";
import {useVendorDashboardStore} from "@/stores/vendor/dashboard.js";

const dashboardStore = useVendorDashboardStore();

onMounted(() => {
  dashboardStore.getDashboardData();
})

const {dashboard} = storeToRefs(dashboardStore);

</script>
