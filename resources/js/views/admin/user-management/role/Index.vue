<template>
    <PageHeader title="Role List" subtitle="Manage user roles" @refresh="fetchRoles(true)">
        <template #actions>
            <router-link
                v-can="'create_role'"
                :to="{name:'admin.role-create'}"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </router-link>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="roles.data"
                        :loading="roles.loading"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ index + 1 }}
                            </template>
                            <template v-else-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <router-link
                                        v-can="'edit_role'"
                                        class="me-2"
                                        :to="{name:'admin.role-edit', params:{id: record.id}}"
                                    >
                                        <i class="ti ti-edit"></i>
                                    </router-link>
                                    <a
                                        v-can="'delete_role'"
                                        href="javascript:void(0);"
                                        @click="deleteRole(record.id)"
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
</template>

<script setup>
import {onMounted} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {useRoleStore} from "@/stores/admin/user-management/role";
import {storeToRefs} from "pinia";

const roleStore = useRoleStore();

onMounted(() => {
  fetchRoles();
})

const {roles} = storeToRefs(roleStore);

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
    title: 'Action',
    key: 'action',
    align: 'center',
  },
];

const fetchRoles = (refetch = false) => {
  if (refetch) {
    roles.value.data = [];
  }

  roleStore.getRoles();
};

const deleteRole = async (id) => {
  Swal.fire({
    title: 'Are You Sure to Delete ? ',
    text: "If you delete this, it will be gone forever.",
    icon:'warning',
    showCancelButton: true,
    confirmButtonColor: 'red',
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await roleStore.deleteRole(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
