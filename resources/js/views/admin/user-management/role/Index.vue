<template>
    <PageHeader title="Role List" subtitle="Manage user roles" @refresh="roleStore.getRoles()">
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
          <table class="table table-bordered">
            <thead>
            <tr>
              <th>SN</th>
              <th>Name</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <VLoader v-if="roles.loading" :colspan="4"/>
            <template v-else-if="roles.data.length">
              <tr v-for="(role,index) in roles.data" :key="index">
                <th>{{ index + 1 }}</th>
                <td>{{ role.name }}</td>
                <td width="180">
                  <router-link v-can="'edit_role'" :to="{name:'admin.role-edit',params:{id:role.id}}" type="button"
                               class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-edit"> Edit</i>
                  </router-link>
                  <button v-can="'delete_role'" @click="deleteRole(role.id)" type="button"
                          class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-trash"> Delete</i>
                  </button>
                </td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="4" class="text-center">
                No Result Found.
              </td>
            </tr>
            </tbody>
          </table>
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
  roleStore.getRoles();
})

const {roles} = storeToRefs(roleStore);

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
