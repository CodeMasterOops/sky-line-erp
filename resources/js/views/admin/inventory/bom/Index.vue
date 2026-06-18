<template>
    <PageHeader
        title="Bill of Materials"
        subtitle="Define material lists for manufactured products"
        @refresh="fetchBoms">
        <template #actions>
            <button
                v-can="'create_bom'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>New BOM
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search BOM name or product"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters">
            <template #filters>
                <div class="dropdown me-2">
                    <a
                        href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ activeLabel }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.is_active = ''">All Status</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.is_active = '1'">Active</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.is_active = '0'">Inactive</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="bomColumns"
                    :data-source="boms.data"
                    :pagination="false"
                    :loading="boms.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (boms.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'product'">
                            {{ record.product_variant?.product?.name }}
                        </template>
                        <template v-else-if="column.key === 'items_count'">
                            {{ record.items?.length ?? 0 }} materials
                        </template>
                        <template v-else-if="column.key === 'is_default'">
                            <span v-if="record.is_default" class="badge bg-primary">Default</span>
                        </template>
                        <template v-else-if="column.key === 'is_active'">
                            <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                {{ record.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="boms.meta" />
            </div>
        </div>
    </div>

    <CreateBom v-model:create-modal-opened="createModalOpened" @saved="fetchBoms" />
    <EditBom v-model:bom-id="editBomId" @saved="fetchBoms" />
    <ViewBom v-model:bom-id="viewBomId" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateBom from './Create.vue';
import EditBom from './Edit.vue';
import ViewBom from './View.vue';
import { useBomStore } from '@/stores/admin/inventory/bom.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { bomColumns, createRowActions } from './tableConfig.js';

const bomStore = useBomStore();
const { boms } = storeToRefs(bomStore);

const createModalOpened = ref(false);
const editBomId = ref(null);
const viewBomId = ref(null);

const { confirmDelete } = useConfirmAction();

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', is_active: '', page: 1, limit: 25 },
    onFilter: (f) => bomStore.getBoms({ filter: f }),
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => boms.value.meta),
    filter,
});

const activeLabel = computed(() => {
    if (filter.is_active === '1') { return 'Active'; }
    if (filter.is_active === '0') { return 'Inactive'; }
    return 'All Status';
});

function fetchBoms() {
    bomStore.getBoms({ filter });
}

const rowActions = createRowActions({
    onView:   (record) => { viewBomId.value = record.id; },
    onEdit:   (id)     => { editBomId.value = id; },
    onDelete: (id)     => confirmDelete(() => bomStore.deleteBom(id)),
});
</script>
