<template>
    <PageHeader title="Variant Attributes" subtitle="Manage your variant attributes" @refresh="fetchWarehouses(true)">
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
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ index + 1 }}
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
                </div>
            </div>
        </div>
    </section>

    <CreateAttribute v-model:create-modal-opened="createModalOpened"/>
    <EditAttribute v-model:attribute_id="edit_attribute_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast.js";
import showErrors from "@/helpers/showErrors.js";
import {storeToRefs} from "pinia";
import CreateAttribute from './Create.vue';
import EditAttribute from './Edit.vue';
import {useAttributeStore} from "@/stores/admin/inventory/attribute.js";

const attributeStore = useAttributeStore();

onMounted(() => {
    attributeStore.getAttributes();
})

const edit_attribute_id = ref('');
const createModalOpened = ref(false);

const {attributes} = storeToRefs(attributeStore);

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Name',
        dataIndex: 'name',
        sorter: {
            compare: (a, b) => {
                a = a.name.toLowerCase();
                b = b.name.toLowerCase();
                return a > b ? -1 : b > a ? 1 : 0;
            },
        },
    },
    {
        title: 'Values',
        key: 'values',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
];

const deleteAttribute = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: "If you delete this, it will be gone forever.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await attributeStore.deleteAttribute(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}
</script>
