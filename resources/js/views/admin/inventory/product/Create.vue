<template>
    <PageHeader title="Add Product" subtitle="Create a stocked product or a billable service">
        <template #actions>
            <router-link :to="{ name: 'admin.product-list' }"
                class="btn btn-outline-secondary d-flex align-items-center">
                <i class="ti ti-arrow-left me-2"></i>
                Back to list
            </router-link>
        </template>
    </PageHeader>

    <ProductForm mode="create" />
</template>

<script setup>
import ProductForm from './ProductForm.vue';
</script>
