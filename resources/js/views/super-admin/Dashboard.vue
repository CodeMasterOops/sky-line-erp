<template>
    <section class="section dashboard mt-2">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="info-box">
          <span class="info-box-icon bg-info elevation-1 text-light">
            <i class="fa fa-user"></i>
          </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Users</span>
                        <span class="info-box-number">{{ dashboard.data.users_count }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
          <span class="info-box-icon bg-secondary elevation-1 text-light">
            <i class="fa fa-image"></i>
          </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Banners</span>
                        <span class="info-box-number">{{ dashboard.data.banners_count }}</span>
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
            <i class="fa fa-users"></i>
          </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Vendors</span>
                        <span class="info-box-number">{{ dashboard.data.vendors_count }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
          <span class="info-box-icon bg-secondary elevation-1 text-light">
            <i class="fa fa-user"></i>
          </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Customers</span>
                        <span class="info-box-number">{{ dashboard.data.customers_count }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
          <span class="info-box-icon bg-info elevation-1 text-light">
            <i class="fa fa-map-pin"></i>
          </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Blogs</span>
                        <span class="info-box-number">{{ dashboard.data.blogs_count }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
          <span class="info-box-icon bg-warning elevation-1 text-light">
            <i class="fa fa-stack-exchange"></i>
          </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Brands</span>
                        <span class="info-box-number">{{ dashboard.data.brands_count }}</span>
                    </div>
                </div>
            </div>
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
        </div>
    </section>
</template>

<script setup>
import {onMounted} from "vue";
import {storeToRefs} from "pinia";
import {useSuperAdminDashboardStore} from "@/stores/super-admin/dashboard.js";

const dashboardStore = useSuperAdminDashboardStore();

onMounted(() => {
    dashboardStore.getDashboardData();
})

const {dashboard} = storeToRefs(dashboardStore);

</script>
