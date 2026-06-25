<template>
    <main>
        <div class="container">
            <section class="section error-404 min-vh-100 d-flex flex-column align-items-center justify-content-center">
                <span class="error-badge">Error</span>
                <h1>404</h1>
                <h2>Page Not Found</h2>
                <p>The page you are looking for doesn't exist or has been moved.</p>
                <router-link
                    :to="{ name: isSuperAdminRoute ? 'super-admin.dashboard' : 'admin.dashboard' }"
                    class="btn btn-back"
                >
                    Back to Home
                </router-link>
                <div class="error-img">
                    <img :src="notFoundImg" class="img-fluid" alt="Page Not Found" />
                </div>
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
