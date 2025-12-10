<template>
    <AppHeader/>
    <AppSidebar/>
    <main id="main" class="main">
        <router-view></router-view>
    </main>
</template>
<script setup>
import AppSidebar from "./AppSidebar.vue";
import AppHeader from "./AppHeader.vue";
</script>
