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

    <router-link :to="{ name: 'admin.support' }" class="support-fab">
        <i class="ti ti-headset me-2"></i>Support
    </router-link>
</template>

<script setup>
import AppSidebar from "./AppSidebar.vue";
import AppHeader from "./AppHeader.vue";
</script>

<style>
.support-fab {
    position: fixed;
    right: 24px;
    bottom: 32px;
    z-index: 2000;
    display: inline-flex;
    align-items: center;
    background: #0339a7;
    color: #fff;
    border-radius: 999px;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 16px rgba(3, 57, 167, 0.4);
    transition: transform 0.18s, box-shadow 0.18s;
    white-space: nowrap;
}

.support-fab:hover {
    transform: translateY(-2px);
    color: #fff;
    box-shadow: 0 6px 20px rgba(3, 57, 167, 0.55);
}

.support-fab i {
    font-size: 18px;
}
</style>
