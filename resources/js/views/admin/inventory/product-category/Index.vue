<template>
    <PageHeader title="Category List" subtitle="Manage your product categories" @refresh="fetch">
        <template #actions>
            <button
                v-can="'create_product_category'"
                type="button"
                @click.prevent="createModalOpened=true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="productCategories.data"
                        :loading="productCategories.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (productCategories.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a class="me-2" href="javascript:void(0);"
                                       @click="edit_product_category_id=record.id">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       @click="deleteProductCategory(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="productCategories.meta" />
                </div>
            </div>
        </div>
    </section>
    <CreateProductCategory v-model:create-modal-opened="createModalOpened" />
    <EditProductCategory v-model:product_category_id="edit_product_category_id" />
</template>

<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateProductCategory from './Create.vue';
import EditProductCategory from './Edit.vue';
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';

const categoryStore = useProductCategoryStore();
const edit_product_category_id = ref('');
const createModalOpened = ref(false);
const { productCategories } = storeToRefs(categoryStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => categoryStore.getProductCategories({ filter }),
    defaults: { page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name' },
    { title: 'Description', dataIndex: 'description' },
    { title: 'Action', key: 'action', align: 'center' },
];

const deleteProductCategory = async (id) => {
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
                let res = await categoryStore.deleteProductCategory(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
