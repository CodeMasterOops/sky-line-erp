<template>
    <div>
        <PageHeader title="Tax List" subtitle="Manage your taxes" @refresh="fetchTaxes(true)">
            <template #actions>
                <button
                    v-can="'create_tax'"
                    type="button"
                    @click.prevent="createModalOpened=true"
                    class="btn btn-primary d-flex align-items-center">
                    <i class="ti ti-circle-plus me-2"></i> Add New
                </button>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar></settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-body">
                        <div class="table-responsive">
                            <a-table
                                class="table datanew table-hover table-center mb-0"
                                :columns="columns"
                                :data-source="taxes.data"
                                :loading="taxes.loading"
                            >
                                <template #bodyCell="{ column, record, index }">
                                    <template v-if="column.key === 'sn'">
                                        {{ index + 1 }}
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="action-icon d-inline-flex">
                                            <span v-if="record.is_system" class="badge bg-secondary me-2" title="Managed by SaaS administrator">System</span>
                                            <template v-else>
                                                <a v-can="'edit_tax'" class="me-2" href="javascript:void(0);"
                                                   @click="edit_tax_id=record.id">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                                <a v-can="'delete_tax'" href="javascript:void(0);"
                                                   @click="deleteTax(record.id)">
                                                    <i class="ti ti-trash"></i>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <CreateTax v-model:create-modal-opened="createModalOpened"/>
    <EditTax v-model:tax_id="edit_tax_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import CreateTax from './Create.vue';
import EditTax from './Edit.vue';
import {useTaxStore} from '@/stores/admin/setting/tax.js';

const taxStore = useTaxStore();

onMounted(() => {
    fetchTaxes();
})

const edit_tax_id = ref('');
const createModalOpened = ref(false);

const {taxes} = storeToRefs(taxStore);

const fetchTaxes = (refetch = false) => {
    taxStore.getTaxes(refetch);
}

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
        title: 'Rate(%)',
        dataIndex: 'rate',
        sorter: {
            compare: (a, b) => {
                a = a.code.toLowerCase();
                b = b.code.toLowerCase();
                return a > b ? -1 : b > a ? 1 : 0;
            },
        },
    },
    {
        title: 'Type',
        dataIndex: 'type_label',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
];

const deleteTax = async (id) => {
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
                let res = await taxStore.deleteTax(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}
</script>
