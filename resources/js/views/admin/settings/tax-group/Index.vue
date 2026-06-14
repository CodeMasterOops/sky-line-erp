<template>
    <div>
        <PageHeader title="Tax Groups" subtitle="Manage tax groups for combined tax rules" @refresh="fetch">
            <template #actions>
                <button
                    type="button"
                    @click.prevent="createModalOpened = true"
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
                                :data-source="taxGroupsList.data"
                                :loading="taxGroupsList.loading"
                                :pagination="false"
                            >
                                <template #bodyCell="{ column, record, index }">
                                    <template v-if="column.key === 'sn'">
                                        {{ (taxGroupsList.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                                    </template>
                                    <template v-if="column.key === 'taxes'">
                                        <span v-for="(member, i) in record.tax_group_members" :key="member.id">
                                            <span class="badge bg-light text-dark border me-1">
                                                {{ member.tax?.name }}
                                                <span v-if="member.tax?.rate != null">({{ member.tax.rate }}%)</span>
                                            </span>
                                        </span>
                                        <span v-if="!record.tax_group_members?.length" class="text-muted">—</span>
                                    </template>
                                    <template v-if="column.key === 'is_active'">
                                        <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ record.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="action-icon d-inline-flex">
                                            <a class="me-2" href="javascript:void(0);" @click="editGroupId = record.id">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <a href="javascript:void(0);" @click="deleteGroup(record.id)">
                                                <i class="ti ti-trash"></i>
                                            </a>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
                            <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="taxGroupsList.meta" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <CreateTaxGroup v-model:create-modal-opened="createModalOpened" @created="fetch" />
    <EditTaxGroup v-model:group_id="editGroupId" @updated="fetch" />
</template>

<script setup>
import {ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import {usePaginatedList} from '@/composables/usePaginatedList.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import CreateTaxGroup from './Create.vue';
import EditTaxGroup from './Edit.vue';

const taxStore = useTaxStore();
const editGroupId = ref('');
const createModalOpened = ref(false);
const {taxGroupsList} = storeToRefs(taxStore);

const {filter, fetch} = usePaginatedList({
    fetchFn: ({filter}) => taxStore.getTaxGroupsList({filter}),
    defaults: {page: 1, limit: 10},
});

const columns = [
    {title: 'SN', key: 'sn', width: 60},
    {title: 'Name', dataIndex: 'name'},
    {title: 'Description', dataIndex: 'description'},
    {title: 'Taxes', key: 'taxes'},
    {title: 'Status', key: 'is_active', align: 'center'},
    {title: 'Action', key: 'action', align: 'center'},
];

const deleteGroup = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete?',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes',
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await taxStore.deleteTaxGroup(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
