<template>
    <AppHeader />
    <AppSidebar />
    <div class="page-wrapper">
        <div class="content">
            <router-view></router-view>
        </div>
        <div class="footer d-sm-flex align-items-center justify-content-between border-top bg-white p-3">
            <p class="mb-0 text-gray-9">
                {{ new Date().getFullYear() }} &copy; Redsky. All Right Reserved
            </p>
            <p>
                Designed &amp; Developed by
                <a href="javascript:void(0);" class="text-primary">Redsky</a>
            </p>
        </div>
    </div>
</template>
<script setup>
import AppSidebar from "./AppSidebar.vue";
import AppHeader from "./AppHeader.vue";
</script>
