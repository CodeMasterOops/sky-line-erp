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

    <!-- /product list -->
    <div class="card table-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <a href="javascript:void(0);" class="btn-searchset"><i
                            class="ti ti-search fs-14 feather-search"></i></a>
                    <input type="search" v-model="filter.search" class="form-control form-control-sm"
                        placeholder="Search" @input="debouncedFetch">
                </div>
            </div>
            <div class="d-flex table-dropdown my-xl-auto right-content align-items-center flex-wrap row-gap-3">
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
                                @click="setFilter('product_category_id', category.id, category.name)">{{
                                    category.name
                                }}</a>
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
            </div>
        </div>
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="columns"
                    :data-source="products.data" :pagination="pagination" :loading="products.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'Product'">
                            <div class="productimgname">
                                <a href="javascript:void(0);" class="avatar avatar-md me-2">
                                    <img :src="record.image || 'https://placehold.co/40x40'" alt="product">
                                </a>
                                <a href="javascript:void(0);">{{ record.name }}</a>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                        @click="editProduct(record.id)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a data-bs-toggle="modal" class="p-2" href="javascript:void(0);"
                                        @click="deleteProduct(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
            </div>
        </div>
    </div>
    <!-- /product list -->
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { useProductStore } from '@/stores/admin/inventory/product.js';
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';
import { useBrandStore } from '@/stores/admin/inventory/brand.js';
import debounce from 'lodash/debounce';

const router = useRouter();
const productStore = useProductStore();
const categoryStore = useProductCategoryStore();
const brandStore = useBrandStore();

const { products } = storeToRefs(productStore);
const { productCategories: categories } = storeToRefs(categoryStore);
const { brands: brandList } = storeToRefs(brandStore);

// Use computed properties to access the data arrays from the store state
const brands = computed(() => brandList.value.data || []);
const categoryList = computed(() => categories.value.data || []);

const filter = reactive({
    search: '',
    product_category_id: '',
    brand_id: '',
    page: 1,
    limit: 10
});

const selectedCategoryName = ref('');
const selectedBrandName = ref('');

const pagination = computed(() => ({
    total: products.value.meta.total,
    current: products.value.meta.current_page,
    pageSize: products.value.meta.per_page,
    showSizeChanger: true,
    showQuickJumper: true,
}));

const columns = [
    {
        title: "SKU",
        dataIndex: "code",
        sorter: true,
    },
    {
        title: "Product Name",
        dataIndex: "name",
        key: "Product",
        sorter: true
    },
    {
        title: "Category",
        dataIndex: "category",
        sorter: true,
    },
    {
        title: "Brand",
        dataIndex: "brand",
        sorter: true,
    },
    {
        title: "Price",
        customRender: ({ record }) => record.defaultVariant?.sales_price,
        sorter: true,
    },
    {
        title: "Unit",
        dataIndex: "unit",
        sorter: true,
    },
    {
        title: "Qty",
        dataIndex: "reorder_quantity",
        sorter: true
    },
    {
        title: "Action",
        key: "action",
    },
];

onMounted(async () => {
    fetchProducts();
    await fetchCategories();
    await fetchBrands();
});

const fetchProducts = () => {
    productStore.getProducts({ filter });
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchProducts();
}, 300);

const fetchCategories = async () => {
    await categoryStore.getProductCategories();
};

const fetchBrands = async () => {
    await brandStore.getBrands();
};

const setFilter = (key, value, name = '') => {
    filter[key] = value;
    if (key === 'product_category_id') selectedCategoryName.value = name;
    if (key === 'brand_id') selectedBrandName.value = name;
    fetchProducts();
};

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchProducts();
};

const editProduct = (id) => {
    router.push({ name: 'admin.product-edit', params: { id: String(id) } });
};

const deleteProduct = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await productStore.deleteProduct(id);
                toast(res.status, res.data.message);
                fetchProducts();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

</script>