<template>
    <section class="section dashboard mt-2">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="info-box">
                      <span class="info-box-icon bg-info elevation-1 text-light">
                        <i class="fa fa-users"></i>
                      </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Customers</span>
                        <span class="info-box-number">{{ dashboard.data.customers_count }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
                  <span class="info-box-icon bg-secondary elevation-1 text-light">
                    <i class="fa fa-product-hunt"></i>
                  </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Products</span>
                        <span class="info-box-number">{{ dashboard.data.products_count }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
                  <span class="info-box-icon bg-success elevation-1 text-light">
                    <i class="fa fa-building-o"></i>
                  </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Warehouse</span>
                        <span class="info-box-number">{{ dashboard.data.warehouse_count }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
                  <span class="info-box-icon bg-primary elevation-1 text-light">
                    <i class="fa fa-user"></i>
                  </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Users</span>
                        <span class="info-box-number">{{ dashboard.data.users_count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAdminDashboardStore } from '@/stores/admin/dashboard';
import { storeToRefs } from 'pinia';

const dashboardStore = useAdminDashboardStore();

onMounted(() => {
    dashboardStore.getDashboardData();
});

const { dashboard } = storeToRefs(dashboardStore);

</script>
