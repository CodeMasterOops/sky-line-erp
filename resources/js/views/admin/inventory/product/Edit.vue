<template>
    <PageHeader title="Edit Product" subtitle="Update product details, pricing, and variants">
        <template #actions>
            <router-link :to="{ name: 'admin.product-list' }"
                class="btn btn-outline-secondary d-flex align-items-center">
                <i class="ti ti-arrow-left me-2"></i>
                Back to list
            </router-link>
        </template>
    </PageHeader>

    <ProductForm mode="edit" :product-id="route.params.id" />
</template>

<script setup>
import { useRoute } from 'vue-router';
import ProductForm from './ProductForm.vue';

const route = useRoute();
</script>
