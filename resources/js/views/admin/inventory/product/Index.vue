<template>
    <PageHeader
        title="Product List"
        subtitle="Manage products and services"
        export-entity="product"
        :get-export-filters="getProductExportFilters"
        @refresh="fetchProducts"
    >
        <template #actions>
            <router-link :to="{ name: 'admin.product-create' }" v-can="'create_product'"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Item
            </router-link>
            <button
                v-can="'import_product'"
                type="button"
                class="btn btn-secondary color d-flex align-items-center"
                @click="openImportWizard"
            >
                <i class="ti ti-upload me-2"></i>
                Import Product
            </button>
        </template>
    </PageHeader>

    <ProductImportWizard ref="importWizardRef" @imported="fetchProducts" />

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search products" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <!-- Type filter -->
                <div class="me-2" style="min-width: 150px;">
                    <VMultiselect
                        id="product_type"
                        v-model="selectedProductType"
                        :options="typeOptions"
                        placeholder="Type"
                    />
                </div>
                <!-- Item role filter -->
                <div class="me-2" style="min-width: 160px;">
                    <VMultiselect
                        id="item_role"
                        v-model="selectedItemRole"
                        :options="itemRoleOptions"
                        placeholder="Item role"
                    />
                </div>
                <!-- Category filter -->
                <div class="me-2" style="min-width: 200px;">
                    <VMultiselect
                        id="product_category_id"
                        v-model="selectedCategoryId"
                        :options="categoryStore.optionsTree"
                        :loading="categoryStore.productCategories.loading"
                        placeholder="Category"
                    />
                </div>
                <!-- Brand filter -->
                <div class="me-2" style="min-width: 160px;">
                    <VMultiselect
                        id="brand_id"
                        v-model="selectedBrandId"
                        :options="brands"
                        :loading="brandStore.brands.loading"
                        placeholder="Brand"
                    />
                </div>
                <!-- Warehouse filter -->
                <div class="me-2" style="min-width: 200px;">
                    <VMultiselect
                        id="warehouse_ids"
                        v-model="selectedWarehouseId"
                        :options="warehouseStore.optionsTree"
                        :loading="warehouseStore.warehouses.loading"
                        placeholder="Warehouse"
                    />
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="columns"
                    :data-source="products.data" :row-key="rowKey" :pagination="false"
                    :loading="products.loading" @change="handleTableChange">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'Product'">
                            <div class="productimgname d-flex align-items-start">
                                <router-link :to="{ name: 'admin.product-show', params: { id: String(record.id) } }" class="avatar avatar-md me-2 flex-shrink-0">
                                    <img :src="record.image || 'https://placehold.co/40x40'" alt="product">
                                </router-link>
                                <div class="min-w-0">
                                    <router-link :to="{ name: 'admin.product-show', params: { id: String(record.id) } }" class="fw-medium d-block text-truncate">
                                        {{ record.name }}
                                    </router-link>
                                    <div v-if="record.code || record.hsn_code" class="small text-muted">
                                        <span v-if="record.code">
                                            Code: <span class="font-monospace">{{ record.code }}</span>
                                        </span>
                                        <span v-if="record.code && record.hsn_code" class="mx-1">·</span>
                                        <span v-if="record.hsn_code">
                                            {{ formatHsnLabel(record.product_type) }}:
                                            <span class="font-monospace">{{ record.hsn_code }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'product_type'">
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge"
                                    :class="record.product_type === 'service' ? 'badge-soft-info' : 'badge-soft-primary'">
                                    {{ formatProductType(record.product_type) }}
                                </span>
                                <span v-if="record.item_role_label" class="badge badge-soft-secondary">
                                    {{ record.item_role_label }}
                                </span>
                                <span v-if="record.is_saleable === false" class="badge badge-soft-warning" title="Hidden from sales documents">
                                    No sale
                                </span>
                                <span v-if="record.is_purchasable === false" class="badge badge-soft-warning" title="Hidden from purchase documents">
                                    No purchase
                                </span>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'total_stock'">
                            <template v-if="record.product_type === 'service'">—</template>
                            <button v-else type="button" class="btn btn-link p-0 align-baseline fw-semibold"
                                @click="openStockDetail(record)">
                                {{ record.total_stock ?? 0 }}
                            </button>
                        </template>

                        <template v-else-if="column.key === 'total_inventory_value'">
                            <template v-if="record.product_type === 'service'">—</template>
                            <template v-else>{{ formatMoney(record.total_inventory_value) }}</template>
                        </template>

                        <template v-else-if="column.key === 'purchase_price'">
                            <template v-if="record.product_type === 'service'">—</template>
                            <template v-else>
                                <div>{{ purchasePriceDisplay(record).price }}</div>
                                <div v-if="purchasePriceDisplay(record).unit" class="small text-muted">
                                    / {{ purchasePriceDisplay(record).unit }}
                                </div>
                            </template>
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
import VMultiselect from '@/components/base/VMultiselect.vue';
import StockDetailModal from './StockDetailModal.vue';
import ProductImportWizard from '@/views/admin/data-transfer/ProductImportWizard.vue';
import { useProductStore } from '@/stores/admin/inventory/product.js';
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';
import { useBrandStore } from '@/stores/admin/inventory/brand.js';
import { useWarehouseStore } from '@/stores/admin/inventory/warehouse.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import {
    getProductColumns,
    createRowActions,
    formatProductType,
    formatHsnLabel,
    formatPriceWithUnit,
} from './tableConfig.js';

const router = useRouter();
const productStore = useProductStore();
const importWizardRef = ref(null);
const categoryStore = useProductCategoryStore();
const brandStore = useBrandStore();
const warehouseStore = useWarehouseStore();

const { products } = storeToRefs(productStore);
const { brands: brandList } = storeToRefs(brandStore);

const brands = computed(() => brandList.value.data || []);

const typeOptions = [
    { id: 'product', name: 'Products' },
    { id: 'service', name: 'Services' },
];

const itemRoleOptions = [
    { id: 'raw_material', name: 'Raw Material' },
    { id: 'semi_finished', name: 'Semi-Finished' },
    { id: 'finished_good', name: 'Finished Good' },
    { id: 'consumable', name: 'Consumable' },
    { id: 'trading_good', name: 'Trading Good' },
];

const stockDetailProduct = ref(null);

const columns = computed(() => getProductColumns({
    showStock: filter.product_type !== 'service',
}));

const fetchProducts = () => productStore.getProducts({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: {
        search: '',
        product_type: '',
        item_role: '',
        product_category_id: '',
        brand_id: '',
        warehouse_ids: '',
        page: 1,
        limit: 10,
        include_inventory_value: 1,
    },
    onFilter: fetchProducts,
});

const selectedProductType = computed({
    get: () => filter.product_type || '',
    set: (val) => { filter.product_type = val ?? ''; },
});

const selectedItemRole = computed({
    get: () => filter.item_role || '',
    set: (val) => { filter.item_role = val ?? ''; },
});

const selectedCategoryId = computed({
    get: () => (filter.product_category_id ? Number(filter.product_category_id) : ''),
    set: (id) => { filter.product_category_id = id ? String(id) : ''; },
});

const selectedBrandId = computed({
    get: () => (filter.brand_id ? Number(filter.brand_id) : ''),
    set: (id) => { filter.brand_id = id ? String(id) : ''; },
});

const selectedWarehouseId = computed({
    get: () => (filter.warehouse_ids ? Number(filter.warehouse_ids) : ''),
    set: (id) => { filter.warehouse_ids = id ? String(id) : ''; },
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => products.value.meta),
    filter,
});

const { confirmDelete } = useConfirmAction();

onMounted(async () => {
    await Promise.all([
        categoryStore.getProductCategories(),
        brandStore.getBrands(),
        warehouseStore.getWarehouses(),
    ]);
});

const rowKey = (row) => row.id;


const openStockDetail = (record) => { stockDetailProduct.value = record; };

const openImportWizard = () => importWizardRef.value?.show();

const getProductExportFilters = () => ({
    search: filter.search,
    product_category_id: filter.product_category_id,
    brand_id: filter.brand_id,
    product_type: filter.product_type,
    item_role: filter.item_role,
    warehouse_ids: filter.warehouse_ids,
});


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

function purchasePriceDisplay(record) {
    return formatPriceWithUnit(record.defaultVariant?.purchase_price, record.unit);
}
</script>
