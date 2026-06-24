<template>
    <PageHeader :title="product?.name || 'Product Details'" subtitle="Product Details">
        <template #actions>
            <router-link :to="{ name: 'admin.product-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <router-link
                v-can="'edit_product'"
                :to="{ name: 'admin.product-edit', params: { id: productId } }"
                class="btn btn-primary"
            >
                <i class="ti ti-edit me-1"></i> Edit
            </router-link>
        </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
        <span class="spinner-border"></span>
    </div>

    <template v-else-if="product">
        <div class="row g-3">
            <!-- Product Info -->
            <div :class="isPhysical ? 'col-lg-8' : 'col-12'">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Product Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3 mb-4">
                            <img
                                :src="product.image || 'https://placehold.co/80x80'"
                                class="rounded border flex-shrink-0"
                                style="width: 80px; height: 80px; object-fit: cover;"
                                alt="Product image"
                            >
                            <div>
                                <h5 class="mb-1">{{ product.name }}</h5>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <span
                                        class="badge"
                                        :class="product.product_type === 'service' ? 'badge-soft-info' : 'badge-soft-primary'"
                                    >
                                        {{ formatProductType(product.product_type) }}
                                    </span>
                                    <span v-if="product.item_role_label" class="badge badge-soft-secondary">
                                        {{ product.item_role_label }}
                                    </span>
                                    <span v-if="product.is_saleable === false" class="badge badge-soft-warning">
                                        No sale
                                    </span>
                                    <span v-if="product.is_purchasable === false" class="badge badge-soft-warning">
                                        No purchase
                                    </span>
                                </div>
                                <p v-if="product.description" class="text-muted small mb-0">{{ product.description }}</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-muted small mb-1">Product Code</div>
                                <div class="font-monospace fw-medium">{{ product.code || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small mb-1">{{ product.product_type === 'service' ? 'SAC Code' : 'HSN Code' }}</div>
                                <div class="font-monospace fw-medium">{{ product.hsn_code || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small mb-1">Category</div>
                                <div>{{ product.category_path || product.category || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small mb-1">Brand</div>
                                <div>{{ product.brand || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small mb-1">Unit of Measure</div>
                                <div>{{ product.unit || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small mb-1">Tax / VAT</div>
                                <div>{{ formatTax(product.tax) }}</div>
                            </div>
                            <template v-if="isPhysical">
                                <div class="col-sm-6">
                                    <div class="text-muted small mb-1">Reorder Quantity</div>
                                    <div>{{ product.reorder_quantity ?? 0 }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small mb-1">Min Stock Level</div>
                                    <div>{{ product.min_stock_level ?? 0 }}</div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Summary -->
            <div v-if="isPhysical" class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Stock Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="display-6 fw-bold text-primary">{{ product.total_stock ?? 0 }}</div>
                            <div class="text-muted small">Total units on hand</div>
                        </div>
                        <div v-if="stockByWarehouse.length" class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Warehouse</th>
                                        <th class="text-end">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in stockByWarehouse" :key="row.warehouse_id">
                                        <td>{{ row.warehouse_name || ('#' + row.warehouse_id) }}</td>
                                        <td class="text-end fw-semibold">{{ row.quantity }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-muted small text-center mb-0 mt-3">
                            No stock on hand.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Variants / Pricing & Stock -->
        <div class="card mt-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">
                    {{ product.has_variants ? 'Variants' : 'Pricing & Stock' }}
                </h5>
                <span class="badge badge-soft-secondary">
                    {{ product.variants?.length ?? 0 }} variant{{ (product.variants?.length ?? 0) !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table datanew mb-0">
                        <thead class="table-light">
                            <tr>
                                <th v-if="product.has_variants">Variant</th>
                                <th>SKU</th>
                                <th>Barcode</th>
                                <th class="text-end">Purchase Price</th>
                                <th class="text-end">Sales Price</th>
                                <th>Tracking</th>
                                <th v-if="isPhysical">Stock by Warehouse</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="variant in product.variants" :key="variant.id">
                                <tr class="align-top">
                                    <td v-if="product.has_variants">
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <template v-if="variant.variant_options?.length">
                                                <span
                                                    v-for="opt in variant.variant_options"
                                                    :key="opt.id"
                                                    class="badge badge-soft-secondary"
                                                >
                                                    {{ opt.attribute_name }}: {{ opt.value }}
                                                </span>
                                            </template>
                                            <span v-else class="text-muted small">Default</span>
                                            <span v-if="variant.is_default" class="badge badge-soft-success">Default</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-monospace">{{ variant.sku || '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="font-monospace">{{ variant.barcode || '—' }}</span>
                                    </td>
                                    <td class="text-end">{{ formatMoney(variant.purchase_price) }}</td>
                                    <td class="text-end">{{ formatMoney(variant.sales_price) }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span v-if="variant.is_serialized" class="badge badge-soft-info">Serialized</span>
                                            <span v-if="variant.is_batch_tracked" class="badge badge-soft-warning">Batch</span>
                                            <span v-if="!variant.is_serialized && !variant.is_batch_tracked" class="text-muted small">—</span>
                                        </div>
                                    </td>
                                    <td v-if="isPhysical">
                                        <template v-if="variant.stocks_by_warehouse?.length">
                                            <div
                                                v-for="s in variant.stocks_by_warehouse"
                                                :key="s.warehouse_id"
                                                class="d-flex justify-content-between gap-3 small py-1 border-bottom last-child-no-border"
                                            >
                                                <span class="text-muted">{{ s.warehouse_name || ('#' + s.warehouse_id) }}</span>
                                                <span class="fw-semibold">{{ s.quantity }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 small pt-1 text-muted">
                                                <span>Total</span>
                                                <span class="fw-bold text-dark">{{ variantTotalStock(variant) }}</span>
                                            </div>
                                        </template>
                                        <span v-else class="text-muted small">No stock</span>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!product.variants?.length">
                                <td :colspan="isPhysical ? (product.has_variants ? 7 : 6) : (product.has_variants ? 6 : 5)" class="text-center text-muted py-3">
                                    No variants found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </template>

    <div v-else-if="!loading" class="text-center text-muted py-5">
        Product not found.
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useProductStore } from '@/stores/admin/inventory/product.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import { formatProductType } from './tableConfig.js';

const route = useRoute();
const productStore = useProductStore();
const { product: productState } = storeToRefs(productStore);

const productId = computed(() => route.params.id);
const product = computed(() => productState.value.data);
const loading = computed(() => productState.value.loading);
const isPhysical = computed(() => product.value?.product_type !== 'service');
const stockByWarehouse = computed(() => product.value?.stock_by_warehouse ?? []);

function variantTotalStock(variant) {
    return variant.stocks_by_warehouse?.reduce((sum, s) => sum + (s.quantity ?? 0), 0) ?? 0;
}

function formatTax(tax) {
    if (!tax?.name) {
        return 'No VAT';
    }
    const rate = tax.rate;
    if (rate !== undefined && rate !== null && rate !== '') {
        return `${tax.name} (${Number(rate)}%)`;
    }
    return tax.name;
}

onMounted(() => {
    productStore.getProduct(productId.value);
});
</script>
