<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Notifications</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Notifications</h5>
      </div>
      <div class="card-body">
        <VDataTable :meta="notifications.meta" v-model:filter="filter" :search-option="false">
          <table class="table table-bordered">
            <thead>
            <tr>
              <th>SN</th>
              <th>Type</th>
              <th>Time</th>
              <th>Read At</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="notifications.loading" :colspan="7"/>
            <template v-else-if="notifications.data.length">
              <tr v-for="(notification,index) in notifications.data" :key="index">
                <th>{{ index + 1 }}</th>
                <td>{{ notification.notification_type }} : {{ notification.data?.notification_title }}</td>
                <td>{{ notification.time }}</td>
                <td>{{ notification.read_at ?? 'Not read' }}</td>
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
import {storeToRefs} from "pinia";
import {useAdminNotificationStore} from "@/stores/admin/notification";

const notificationStore = useAdminNotificationStore();

onMounted(() => {
  fetchNotifications();
})

const {allNotifications: notifications} = storeToRefs(notificationStore);

const filter = reactive({})

watch(() => filter, () => {
  fetchNotifications();
}, {deep: true})

const fetchNotifications = () => {
  notificationStore.getAllNotifications({filter});
}
</script>
