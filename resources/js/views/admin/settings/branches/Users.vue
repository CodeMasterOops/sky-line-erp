<template>
    <div>
        <PageHeader
            :title="`Branch Users — ${branch?.name ?? '...'}`"
            subtitle="Manage which users can access this branch and their roles"
            @refresh="loadAssignments"
        >
            <template #actions>
                <router-link
                    :to="{ name: 'admin.branch-list' }"
                    class="btn btn-outline-secondary me-2"
                >
                    <i class="ti ti-arrow-left me-1"></i> Back
                </router-link>
                <button type="button" class="btn btn-primary" @click="openAssignModal">
                    <i class="ti ti-user-plus me-2"></i> Assign User
                </button>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar />
                <div class="card flex-fill mb-0">
                    <div class="card-body">
                        <div class="table-responsive">
                            <a-table
                                :columns="columns"
                                :data-source="assignments"
                                :loading="loading"
                                :pagination="false"
                                row-key="id"
                            >
                                <template #bodyCell="{ column, record }">
                                    <template v-if="column.key === 'role'">
                                        <span v-if="record.branch_role">{{ record.branch_role.name }}</span>
                                        <span v-else class="text-muted">—</span>
                                    </template>
                                    <template v-if="column.key === 'status'">
                                        <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ record.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="d-flex gap-2">
                                            <a
                                                href="#"
                                                class="text-warning"
                                                title="Edit assignment"
                                                @click.prevent="openEditModal(record)"
                                            ><i class="ti ti-edit"></i></a>
                                            <a
                                                href="#"
                                                class="text-danger"
                                                title="Remove from branch"
                                                @click.prevent="removeUser(record)"
                                            ><i class="ti ti-user-minus"></i></a>
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

    <VModal
        :show-modal="showModal"
        :title="editingUser ? 'Edit Branch Assignment' : 'Assign User to Branch'"
        size="md"
        @close-click="closeModal"
    >
        <template #modal-body>
            <form @submit.prevent="saveAssignment" class="row g-3">
                <div v-if="!editingUser" class="col-12">
                    <label class="form-label">User <span class="text-danger">*</span></label>
                    <select v-model="form.user_id" class="form-select" required>
                        <option value="">Select user...</option>
                        <option
                            v-for="u in availableUsers"
                            :key="u.id"
                            :value="u.id"
                        >{{ u.name }} ({{ u.email }})</option>
                    </select>
                </div>
                <div v-else class="col-12">
                    <label class="form-label">User</label>
                    <input :value="editingUser.name" class="form-control" disabled />
                </div>
                <div class="col-12">
                    <label class="form-label">Branch Role</label>
                    <select v-model="form.role_id" class="form-select">
                        <option :value="null">No specific role (inherit company role)</option>
                        <option v-for="r in roles.data" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                </div>
                <div v-if="editingUser" class="col-12">
                    <div class="form-check form-switch">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="form-check-input"
                            id="assignmentActive"
                        />
                        <label class="form-check-label" for="assignmentActive">Active</label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-danger me-2" @click="closeModal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        {{ editingUser ? 'Update' : 'Assign' }}
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { apiAdmin } from '@/helpers/api';
import { useBranchStore } from '@/stores/admin/settings/branch.js';
import { useUserStore } from '@/stores/admin/user-management/user.js';
import { useRoleStore } from '@/stores/admin/user-management/role.js';

const route = useRoute();
const branchId = computed(() => Number(route.params.branchId));

const branchStore = useBranchStore();
const userStore = useUserStore();
const roleStore = useRoleStore();

const { branches } = storeToRefs(branchStore);
const { users } = storeToRefs(userStore);
const { roles } = storeToRefs(roleStore);

const branch = computed(() => branches.value.data.find(b => b.id === branchId.value) ?? null);

const assignments = ref([]);
const loading = ref(false);
const showModal = ref(false);
const saving = ref(false);
const editingUser = ref(null);

const form = ref({ user_id: '', role_id: null, is_active: true });

const assignedUserIds = computed(() => assignments.value.map(a => a.id));
const availableUsers = computed(() =>
    (users.value.data ?? []).filter(u => !assignedUserIds.value.includes(u.id))
);

const columns = [
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Email', dataIndex: 'email', key: 'email' },
    { title: 'Branch Role', key: 'role' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action' },
];

const loadAssignments = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin(`branch/${branchId.value}/users`);
        assignments.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await Promise.all([
        loadAssignments(),
        branchStore.getBranches(),
        userStore.getUsers(),
        roleStore.getRoles(),
    ]);
});

const openAssignModal = () => {
    editingUser.value = null;
    form.value = { user_id: '', role_id: null, is_active: true };
    showModal.value = true;
};

const openEditModal = (record) => {
    editingUser.value = record;
    form.value = {
        user_id: record.id,
        role_id: record.branch_role?.id ?? null,
        is_active: record.is_active,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingUser.value = null;
};

const saveAssignment = async () => {
    saving.value = true;
    try {
        if (editingUser.value) {
            await apiAdmin(`branch/${branchId.value}/users/${editingUser.value.id}`, 'patch', {
                user_id: editingUser.value.id,
                role_id: form.value.role_id,
                is_active: form.value.is_active,
            });
            toast(200, 'Assignment updated successfully.');
        } else {
            await apiAdmin(`branch/${branchId.value}/users`, 'post', {
                user_id: form.value.user_id,
                role_id: form.value.role_id,
            });
            toast(201, 'User assigned to branch successfully.');
        }
        closeModal();
        await loadAssignments();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const removeUser = async (record) => {
    const result = await Swal.fire({
        title: 'Remove user?',
        text: `Remove ${record.name} from this branch?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Remove',
        confirmButtonColor: '#d33',
    });

    if (!result.isConfirmed) return;

    try {
        await apiAdmin(`branch/${branchId.value}/users/${record.id}`, 'delete');
        toast(200, 'User removed from branch.');
        await loadAssignments();
    } catch (e) {
        showErrors(e);
    }
};
</script>
