<template>
    <PageHeader
        title="Company Categories"
        subtitle="Industries and the modules each one starts with"
        @refresh="fetch"
    >
        <template #actions>
            <button type="button" class="btn btn-primary" @click.prevent="openCreate">
                <i class="ti ti-circle-plus me-1"></i>Add Category
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <div class="search-set">
                    <div class="search-input">
                        <a href="javascript:void(0);" class="btn-searchset">
                            <i class="ti ti-search fs-14 feather-search"></i>
                        </a>
                        <input
                            v-model="filter.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search categories"
                            @input="debouncedFetch"
                        />
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="categories.data"
                        :loading="categories.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (categories.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'name'">
                                <div class="d-flex align-items-center gap-2">
                                    <i v-if="record.icon" :class="record.icon"></i>
                                    <div>
                                        <div class="fw-medium">{{ record.name }}</div>
                                        <div class="fs-11 text-muted">{{ record.description }}</div>
                                    </div>
                                </div>
                            </template>
                            <template v-if="column.key === 'modules'">
                                <span
                                    v-for="key in record.modules"
                                    :key="key"
                                    class="badge badge-sm bg-light text-dark me-1 mb-1"
                                >{{ moduleName(key) }}</span>
                                <span v-if="!record.modules?.length" class="text-muted">Core only</span>
                            </template>
                            <template v-if="column.key === 'companies'">
                                {{ record.companies_count ?? 0 }}
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="['badge badge-sm', record.is_active ? 'badge-success' : 'badge-danger']">
                                    {{ record.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="record.is_default" class="badge badge-sm badge-info ms-1">Default</span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a href="javascript:void(0);" class="me-2" @click="openEdit(record)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="!record.is_default"
                                        href="javascript:void(0);"
                                        @click="destroy(record)"
                                    >
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>

                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="categories.meta" />
                </div>
            </div>
        </div>
    </section>

    <CategoryForm v-model:show="formOpened" :category="editing" @saved="fetch" />
</template>

<script setup>
import { onMounted, ref } from 'vue';
import debounce from 'lodash/debounce';
import Swal from 'sweetalert2';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CategoryForm from './Form.vue';
import { useModuleStore } from '@/stores/super-admin/module';

const moduleStore = useModuleStore();
const { categories, catalogue } = storeToRefs(moduleStore);

const formOpened = ref(false);
const editing = ref(null);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => moduleStore.getCategories({ filter }),
    defaults: { search: '', page: 1, limit: 10 },
});

onMounted(() => moduleStore.getCatalogue());

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Category', key: 'name' },
    { title: 'Default Modules', key: 'modules' },
    { title: 'Companies', key: 'companies', align: 'right' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action', align: 'center' },
];

const moduleName = (key) => catalogue.value.data.find((m) => m.key === key)?.name ?? key;

const debouncedFetch = debounce(() => {
    const onFirstPage = filter.page === 1;
    filter.page = 1;
    if (onFirstPage) {
        fetch();
    }
}, 300);

const openCreate = () => {
    editing.value = null;
    formOpened.value = true;
};

const openEdit = (record) => {
    editing.value = record;
    formOpened.value = true;
};

const destroy = (record) => {
    Swal.fire({
        title: `Delete "${record.name}"?`,
        text: 'Companies already using this category keep their modules.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (!result.value) {
            return;
        }

        try {
            const res = await moduleStore.deleteCategory(record.id);
            toast(res.status, res.data.message);
            fetch();
        } catch (e) {
            showErrors(e);
        }
    });
};
</script>
