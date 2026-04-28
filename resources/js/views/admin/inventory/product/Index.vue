<template>
    <PageHeader title="Product List" subtitle="Manage your products" @refresh="fetchProducts">
        <template #actions>
            <router-link :to="{ name: 'admin.product-create' }" v-can="'create_product'"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Product
            </router-link>
            <a href="#" class="btn btn-secondary color d-flex align-items-center" data-bs-toggle="modal"
                data-bs-target="#view-notes">
                <i class="ti ti-download me-2"></i>
                Import Product
            </a>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search products" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <!-- Category Dropdown -->
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ selectedCategoryName || 'Category' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('product_category_id', '')">All Categories</a>
                        </li>
                        <li v-for="category in categoryList" :key="category.id">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('product_category_id', category.id, category.name)">
                                {{ category.name }}
                            </a>
                        </li>
                    </ul>
                </div>
                <!-- Brand Dropdown -->
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ selectedBrandName || 'Brand' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('brand_id', '')">All Brands</a>
                        </li>
                        <li v-for="brand in brands" :key="brand.id">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('brand_id', brand.id, brand.name)">{{ brand.name }}</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="productColumns"
                    :data-source="products.data" :row-key="rowKey" :pagination="false"
                    :loading="products.loading" @change="handleTableChange">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'Product'">
                            <div class="productimgname">
                                <a href="javascript:void(0);" class="avatar avatar-md me-2">
                                    <img :src="record.image || 'https://placehold.co/40x40'" alt="product">
                                </a>
                                <a href="javascript:void(0);">{{ record.name }}</a>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'total_stock'">
                            <button type="button" class="btn btn-link p-0 align-baseline fw-semibold"
                                @click="openStockDetail(record)">
                                {{ record.total_stock ?? 0 }}
                            </button>
                        </template>

                        <template v-else-if="column.key === 'total_inventory_value'">
                            {{ formatMoney(record.total_inventory_value) }}
                        </template>

                        <template v-else-if="column.key === 'tax'">
                            {{ formatProductTax(record) }}
                        </template>

                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="products.meta" />
            </div>
        </div>
    </div>

    <StockDetailModal v-model:product="stockDetailProduct" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import StockDetailModal from './StockDetailModal.vue';
import { useProductStore } from '@/stores/admin/inventory/product.js';
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';
import { useBrandStore } from '@/stores/admin/inventory/brand.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import { productColumns, createRowActions } from './tableConfig.js';

const router = useRouter();
const productStore = useProductStore();
const categoryStore = useProductCategoryStore();
const brandStore = useBrandStore();

const { products } = storeToRefs(productStore);
const { productCategories: categories } = storeToRefs(categoryStore);
const { brands: brandList } = storeToRefs(brandStore);

const brands = computed(() => brandList.value.data || []);
const categoryList = computed(() => categories.value.data || []);

const selectedCategoryName = ref('');
const selectedBrandName = ref('');
const stockDetailProduct = ref(null);

const fetchProducts = () => productStore.getProducts({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: {
        search: '',
        product_category_id: '',
        brand_id: '',
        page: 1,
        limit: 10,
        include_inventory_value: 1,
    },
    onFilter: fetchProducts,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => products.value.meta),
    filter,
});

const { confirmDelete } = useConfirmAction();

onMounted(async () => {
    await Promise.all([categoryStore.getProductCategories(), brandStore.getBrands()]);
});

const rowKey = (row) => row.id;

const setFilter = (key, value, name = '') => {
    filter[key] = value;
    if (key === 'product_category_id') selectedCategoryName.value = name;
    if (key === 'brand_id') selectedBrandName.value = name;
};

const openStockDetail = (record) => { stockDetailProduct.value = record; };

const editProduct = (id) => {
    router.push({ name: 'admin.product-edit', params: { id: String(id) } });
};

const handleDelete = (id) => {
    confirmDelete(
        () => productStore.deleteProduct(id),
        fetchProducts,
    );
};

const rowActions = createRowActions({
    onEdit:   editProduct,
    onDelete: handleDelete,
});

function formatProductTax(record) {
    const t = record?.tax;
    if (!t?.name) return 'No Vat';
    const rate = t.rate;
    if (rate !== undefined && rate !== null && rate !== '') {
        return `${t.name} (${Number(rate)}%)`;
    }
    return t.name;
}
</script>
