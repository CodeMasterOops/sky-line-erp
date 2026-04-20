<template>
    <PageHeader title="User List" subtitle="Manage your users" @refresh="fetchUsers(true)">
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
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ index + 1 }}
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
                </div>
            </div>
        </div>
    </section>
    <CreateUser v-model:create-modal-opened="createModalOpened"/>
    <EditUser v-model:user_id="edit_user_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import {useUserStore} from "@/stores/admin/user-management/user";
import CreateUser from './Create.vue';
import EditUser from './Edit.vue';

const userStore = useUserStore();

onMounted(() => {
    fetchUsers();
})

const edit_user_id = ref('');
const createModalOpened = ref(false);

const {users} = storeToRefs(userStore);

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
        title: 'Email',
        dataIndex: 'email',
    },
    {
        title: 'Role',
        key: 'roles',
    },
    {
        title: 'Status',
        key: 'status',
        align: 'center',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
];

const fetchUsers = (refetch = false) => {
    if (refetch) {
        users.value.data = [];
    }

    userStore.getUsers();
};

const deleteUser = async (id) => {
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
                let res = await userStore.deleteUser(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}

const updateStatus = async (id) => {
    Swal.fire({
        text: "Are you sure you want to change the status?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: "red",
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await userStore.updateStatus(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}
</script>
