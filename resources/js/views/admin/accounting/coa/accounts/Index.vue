<template>
    <PageHeader title="Accounts" subtitle="Manage Accounts" @refresh="fetch">
        <template #actions>
            <button
                v-can="'create_account'"
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
                :data-source="accounts.data"
                :loading="accounts.loading"
                :pagination="false"
            >
                <template #bodyCell="{ column, record, index }">
                    <template v-if="column.key === 'sn'">
                        {{ (accounts.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                    </template>
                    <template v-if="column.key === 'account_group'">
                        {{ record.account_group?.name || '-' }}
                    </template>
                    <template v-if="column.key === 'action'">
                        <div class="action-icon d-inline-flex">
                            <a class="me-2" href="javascript:void(0);"
                               @click="edit_account_id=record.id">
                                <i class="ti ti-edit"></i>
                            </a>
                            <a href="javascript:void(0);"
                               @click="deleteAccount(record.id)">
                                <i class="ti ti-trash"></i>
                            </a>
                        </div>
                    </template>
                </template>
            </a-table>
            <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="accounts.meta" />
        </div>
    </section>
    <CreateAccount v-model:create-modal-opened="createModalOpened"/>
    <EditAccount v-model:account_id="edit_account_id"/>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateAccount from './Create.vue';
import EditAccount from './Edit.vue';
import { useAccountStore } from '@/stores/admin/accounting/account.js';
import { useAccountGroupStore } from '@/stores/admin/accounting/account-group.js';

const accountStore = useAccountStore();
const accountGroupStore = useAccountGroupStore();
const edit_account_id = ref('');
const createModalOpened = ref(false);
const { accounts } = storeToRefs(accountStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => accountStore.getAccounts({ filter }),
    defaults: { page: 1, limit: 10 },
});

onMounted(() => {
    accountGroupStore.getAccountGroups();
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name' },
    { title: 'Code', dataIndex: 'code' },
    { title: 'Category', dataIndex: 'category' },
    { title: 'Account Group', key: 'account_group' },
    { title: 'Action', key: 'action', align: 'center' },
];

const deleteAccount = async (id) => {
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
                let res = await accountStore.deleteAccount(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
