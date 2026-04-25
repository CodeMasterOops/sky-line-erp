<template>
  <PageHeader
    title="Profile"
    subtitle="Manage your profile"
    hide-action-buttons
  />
  <div class="row">
    <div class="col-12">
      <EditProfile />
    </div>
  </div>
</template>

<script setup>
import EditProfile from "./EditProfile.vue";
</script>
