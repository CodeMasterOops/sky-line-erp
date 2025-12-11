<template>
    <section class="section dashboard mt-2">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="info-box">
                      <span class="info-box-icon bg-info elevation-1 text-light">
                        <i class="fa fa-user"></i>
                      </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Company</span>
                        <span class="info-box-number">0</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="info-box">
                      <span class="info-box-icon bg-info elevation-1 text-light">
                        <i class="fa fa-list"></i>
                      </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Fiscal Year</span>
                        <span class="info-box-number">0</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useSuperAdminDashboardStore } from '@/stores/super-admin/dashboard.js';

const dashboardStore = useSuperAdminDashboardStore();

onMounted(() => {
    dashboardStore.getDashboardData();
});

const { dashboard } = storeToRefs(dashboardStore);

</script>
