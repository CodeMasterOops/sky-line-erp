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
    </div>
  </section>
</template>

<script setup>
import {onMounted} from "vue";
import {useAdminDashboardStore} from "@/stores/admin/dashboard";
import {storeToRefs} from "pinia";

const dashboardStore = useAdminDashboardStore();

onMounted(() => {
  dashboardStore.getDashboardData();
})

const {dashboard} = storeToRefs(dashboardStore);

</script>
