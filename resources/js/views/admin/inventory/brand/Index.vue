<template>
    <PageHeader title="Brand List" subtitle="Manage your brands" @refresh="fetch">
        <template #actions>
            <button
                v-can="'create_brand'"
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
                        :data-source="brands.data"
                        :loading="brands.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (brands.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a class="me-2" href="javascript:void(0);"
                                       @click="edit_brand_id=record.id">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       @click="deleteBrand(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="brands.meta" />
                </div>
            </div>
        </div>
    </section>
    <CreateBrand v-model:create-modal-opened="createModalOpened" />
    <EditBrand v-model:brand_id="edit_brand_id" />
</template>

<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateBrand from './Create.vue';
import EditBrand from './Edit.vue';
import { useBrandStore } from '@/stores/admin/inventory/brand.js';

const brandStore = useBrandStore();
const edit_brand_id = ref('');
const createModalOpened = ref(false);
const { brands } = storeToRefs(brandStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => brandStore.getBrands({ filter }),
    defaults: { page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name' },
    { title: 'Code', dataIndex: 'code' },
    { title: 'Action', key: 'action', align: 'center' },
];

const deleteBrand = async (id) => {
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
                let res = await brandStore.deleteBrand(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
