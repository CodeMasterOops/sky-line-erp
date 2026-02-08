<template>
    <PageHeader title="User List" subtitle="Manage your users" @refresh="userStore.getUsers()">
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
          <table class="table table-bordered">
            <thead>
            <tr>
              <th>SN</th>
              <th>Name</th>
              <th>Contact</th>
              <th>Role</th>
              <th>Status</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="users.loading" :colspan="6"/>
            <template v-else-if="users.data.length">
              <tr v-for="(user,index) in users.data" :key="index">
                <th>{{ index + 1 }}</th>
                <td>
                  {{ user.name }}
                </td>
                <td>
                  <template v-if="user.email">
                    <i class="fa fa-envelope-o text-primary"></i> {{ user.email }} <br>
                  </template>
                  <template v-if="user.phone">
                    <i class="fa fa-phone text-success"></i> {{ user.phone }}
                  </template>
                </td>
                <td>
                  <span v-for="(role,key) in user.roles" :key="key"
                        class="badge bg-primary mx-1"> {{ role.name }}</span>
                </td>
                <td>
                  <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        @click.prevent="updateStatus(user.id)"
                        type="checkbox"
                        :id="'switch'+index" :checked="user.status">
                    <label class="form-check-label" :for="'switch'+index"></label>
                  </div>
                </td>
                <td style="width:90px;">
                  <button
                      v-can="'edit_user'"
                      type="button"
                      @click.prevent="edit_user_id=user.id"
                      class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-edit"> </i>
                  </button>
                  <button v-can="'delete_user'" @click="deleteUser(user.id)" type="button"
                          class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-trash"> </i>
                  </button>
                </td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="6" class="text-center">
                No Result Found.
              </td>
            </tr>
            </tbody>
          </table>
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
  userStore.getUsers();
})

const edit_user_id = ref('');
const createModalOpened = ref(false);

const {users} = storeToRefs(userStore);

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
