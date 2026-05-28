<template>
    <PageHeader title="Variant Attributes" subtitle="Manage your variant attributes" @refresh="fetch">
        <template #actions>
            <button
                v-can="'create_attribute'"
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
                        :data-source="attributes.data"
                        :loading="attributes.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (attributes.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key==='values'">
                                <span v-for="attrVal in record.values" :key="`${record.id}-${attrVal.id}`"
                                      class="badge bg-secondary mx-1">{{ attrVal.value }}</span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a class="me-2" href="javascript:void(0);"
                                       @click="edit_attribute_id=record.id">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       @click="deleteAttribute(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="attributes.meta" />
                </div>
            </div>
        </div>
    </section>

    <CreateAttribute v-model:create-modal-opened="createModalOpened"/>
    <EditAttribute v-model:attribute_id="edit_attribute_id"/>
</template>

<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateAttribute from './Create.vue';
import EditAttribute from './Edit.vue';
import { useAttributeStore } from '@/stores/admin/inventory/attribute.js';

const attributeStore = useAttributeStore();
const edit_attribute_id = ref('');
const createModalOpened = ref(false);
const { attributes } = storeToRefs(attributeStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => attributeStore.getAttributes({ filter }),
    defaults: { page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name' },
    { title: 'Values', key: 'values' },
    { title: 'Action', key: 'action', align: 'center' },
];

const deleteAttribute = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes',
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await attributeStore.deleteAttribute(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
