<template>
    <PageHeader title="Account Groups" subtitle="Manage Account Groups" @refresh="fetch">
        <template #actions>
            <button
                v-can="'create_account_group'"
                type="button"
                @click.prevent="createModalOpened=true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="table-responsive">
            <a-table
                class="table datanew table-hover table-center mb-0"
                :columns="columns"
                :data-source="accountGroups.data"
                :loading="accountGroups.loading"
                :pagination="false"
            >
                <template #bodyCell="{ column, record, index }">
                    <template v-if="column.key === 'sn'">
                        {{ (accountGroups.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                    </template>
                    <template v-if="column.key === 'action'">
                        <div class="action-icon d-inline-flex">
                            <a class="me-2" href="javascript:void(0);"
                               @click="edit_account_group_id=record.id">
                                <i class="ti ti-edit"></i>
                            </a>
                            <a href="javascript:void(0);"
                               @click="deleteAccountGroup(record.id)">
                                <i class="ti ti-trash"></i>
                            </a>
                        </div>
                    </template>
                </template>
            </a-table>
            <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="accountGroups.meta" />
        </div>
    </section>
    <CreateAccountGroup v-model:create-modal-opened="createModalOpened"/>
    <EditAccountGroup v-model:account_group_id="edit_account_group_id"/>
</template>

<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateAccountGroup from './Create.vue';
import EditAccountGroup from './Edit.vue';
import { useAccountGroupStore } from '@/stores/admin/accounting/account-group.js';

const accountGroupStore = useAccountGroupStore();
const edit_account_group_id = ref('');
const createModalOpened = ref(false);
const { accountGroups } = storeToRefs(accountGroupStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => accountGroupStore.getAccountGroups({ filter }),
    defaults: { page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name' },
    { title: 'Code', dataIndex: 'code' },
    { title: 'Description', key: 'description' },
    { title: 'Action', key: 'action', align: 'center' },
];

const deleteAccountGroup = async (id) => {
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
                let res = await accountGroupStore.deleteAccountGroup(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
