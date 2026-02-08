<template>
    <AppHeader />
    <AppSidebar />
    <div class="page-wrapper">
        <div class="content">
            <router-view></router-view>
        </div>
    </div>
</template>
<script setup>
import AppSidebar from "./AppSidebar.vue";
import AppHeader from "./AppHeader.vue";
</script>
