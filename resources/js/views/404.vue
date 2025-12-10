<template>
    <main>
        <div class="container">
            <section
                class="section error-404 min-vh-100 d-flex flex-column align-items-center justify-content-center"
            >
                <h1>404</h1>
                <h2>The page you are looking for doesn't exist.</h2>
                <router-link :to="{ name:isSuperAdminRoute ? 'super-admin.dashboard' : 'user.dashboard' }" class="btn">
                    Back to home
                </router-link>
                <img :src="notFoundImg" class="img-fluid py-5" alt="Page Not Found"/>
            </section>
        </div>
    </main>
</template>

<script setup>
import notFoundImg from '@/assets/404.svg';
import {computed} from "vue";
import {useRoute} from "vue-router";

const route = useRoute();

const isSuperAdminRoute = computed(() => {
    return /^super-admin\./.test(route.name);
});
</script>
