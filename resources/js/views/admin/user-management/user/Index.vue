<template>
    <PageHeader title="User List" subtitle="Manage your users" @refresh="fetch">
        <template #actions>
            <button
                v-can="'create_user'"
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
                        :data-source="users.data"
                        :loading="users.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (users.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-else-if="column.key === 'roles'">
                                <template v-if="record.roles?.length">
                                    <span
                                        v-for="role in record.roles"
                                        :key="role.id"
                                        class="badge bg-primary mx-1"
                                    >
                                        {{ role.name }}
                                    </span>
                                </template>
                                <span v-else>-</span>
                            </template>
                            <template v-else-if="column.key === 'branches'">
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="record.branches_count > 0 ? 'btn-outline-primary' : 'btn-outline-warning'"
                                    @click="openManageBranches(record)"
                                    :title="record.branches_count > 0 ? 'Manage branch access' : 'No branches assigned — click to assign'"
                                >
                                    <i class="ti ti-building me-1"></i>
                                    {{ record.branches_count > 0 ? record.branches_count : 'None' }}
                                </button>
                            </template>
                            <template v-else-if="column.key === 'status'">
                                <div class="form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        :id="`switch${index}`"
                                        :checked="record.status"
                                        @click.prevent="updateStatus(record.id)"
                                    >
                                    <label class="form-check-label" :for="`switch${index}`"></label>
                                </div>
                            </template>
                            <template v-else-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a
                                        v-can="'edit_user'"
                                        class="me-2"
                                        href="javascript:void(0);"
                                        @click.prevent="edit_user_id=record.id"
                                    >
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-can="'delete_user'"
                                        href="javascript:void(0);"
                                        @click="deleteUser(record.id)"
                                    >
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="users.meta" />
                </div>
            </div>
        </div>
    </section>

    <CreateUser v-model:create-modal-opened="createModalOpened"/>
    <EditUser v-model:user_id="edit_user_id"/>

    <VModal
        :show-modal="!!manageAccessUser"
        @close-click="closeManageBranches"
        modal-class="extra-medium-modal"
        :title="`Branch Access — ${manageAccessUser?.name ?? ''}`"
    >
        <template #modal-body>
            <VLoader v-if="branchAccessLoading" loader-type="progress" />
            <div v-else>
                <p class="text-muted small mb-3">Select which branches this user can access.</p>
                <div v-if="!branches.data.length" class="text-muted fst-italic small">No branches configured yet.</div>
                <div v-else class="row g-2">
                    <div v-for="branch in branches.data" :key="branch.id" class="col-sm-6">
                        <label
                            class="d-flex align-items-center gap-2 p-2 rounded border"
                            :class="manageAccessBranchIds.includes(branch.id)
                                ? 'border-primary bg-primary bg-opacity-10'
                                : 'border-secondary border-opacity-25'"
                            style="cursor:pointer"
                        >
                            <input
                                type="checkbox"
                                class="form-check-input mt-0 flex-shrink-0"
                                :value="branch.id"
                                v-model="manageAccessBranchIds"
                            />
                            <span class="fw-medium small">{{ branch.name }}</span>
                            <span v-if="branch.is_head_office" class="badge bg-warning text-dark ms-auto small">HQ</span>
                        </label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-cancel" @click="closeManageBranches">Cancel</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="savingBranchAccess"
                        @click="saveBranchAccess"
                    >
                        <span v-if="savingBranchAccess" class="spinner-border spinner-border-sm me-1"></span>
                        Save Access
                    </button>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { apiAdmin } from '@/helpers/api';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import { useUserStore } from '@/stores/admin/user-management/user';
import { useBranchStore } from '@/stores/admin/settings/branch';
import CreateUser from './Create.vue';
import EditUser from './Edit.vue';

const userStore = useUserStore();
const branchStore = useBranchStore();
const edit_user_id = ref('');
const createModalOpened = ref(false);
const { users } = storeToRefs(userStore);
const { branches } = storeToRefs(branchStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => userStore.getUsers({ filter }),
    defaults: { page: 1, limit: 10 },
});

branchStore.getBranches();

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name' },
    { title: 'Email', dataIndex: 'email' },
    { title: 'Role', key: 'roles' },
    { title: 'Branches', key: 'branches', align: 'center' },
    { title: 'Status', key: 'status', align: 'center' },
    { title: 'Action', key: 'action', align: 'center' },
];

const manageAccessUser = ref(null);
const manageAccessBranchIds = ref([]);
const originalAccessBranchIds = ref([]);
const branchAccessLoading = ref(false);
const savingBranchAccess = ref(false);

const openManageBranches = async (record) => {
    manageAccessUser.value = record;
    branchAccessLoading.value = true;
    try {
        await userStore.getUser(record.id);
        manageAccessBranchIds.value = [...(userStore.user.data.branch_ids ?? [])];
        originalAccessBranchIds.value = [...(userStore.user.data.branch_ids ?? [])];
    } catch (e) {
        showErrors(e);
    } finally {
        branchAccessLoading.value = false;
    }
};

const closeManageBranches = () => {
    manageAccessUser.value = null;
    manageAccessBranchIds.value = [];
    originalAccessBranchIds.value = [];
};

const saveBranchAccess = async () => {
    const userId = manageAccessUser.value.id;
    const toAdd = manageAccessBranchIds.value.filter(id => !originalAccessBranchIds.value.includes(id));
    const toRemove = originalAccessBranchIds.value.filter(id => !manageAccessBranchIds.value.includes(id));

    savingBranchAccess.value = true;
    try {
        await Promise.all([
            ...toAdd.map(branchId =>
                apiAdmin(`branch/${branchId}/users`, 'post', {user_id: userId}).catch(() => {})
            ),
            ...toRemove.map(branchId =>
                apiAdmin(`branch/${branchId}/users/${userId}`, 'delete').catch(() => {})
            ),
        ]);
        toast(200, 'Branch access updated successfully.');
        closeManageBranches();
        fetch();
    } catch (e) {
        showErrors(e);
    } finally {
        savingBranchAccess.value = false;
    }
};

const deleteUser = async (id) => {
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
                let res = await userStore.deleteUser(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const updateStatus = async (id) => {
    Swal.fire({
        text: 'Are you sure you want to change the status?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes',
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await userStore.updateStatus(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
