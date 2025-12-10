<template>
  <VModal
      :show-modal="!!createModalOpened"
      @close-click="createModalOpened=false"
      modal-class="extra-medium-modal"
      title="Add New User">
    <template #modal-body>
      <form @submit.prevent="storeUser" class="row g-3">
        <div class="col-md-6">
          <VInput
              id="name"
              v-model="form.name"
              label="Name"
              @validate="validateField('name')"
              :error="errors.name"
          />
        </div>
        <div class="col-md-6">
          <VInput
              id="email"
              v-model="form.email"
              label="Email"
              @validate="validateField('email')"
              :error="errors.email"
          />
        </div>
        <div class="col-md-6">
          <VInput
              id="phone"
              v-model="form.phone"
              label="Phone"
              @validate="validateField('phone')"
              :error="errors.phone"
          />
        </div>
        <div class="col-md-6">
          <VMultiselect
              id="roles"
              v-model="form.roles"
              :options="roles.data"
              :loading="roles.loading"
              mode="multiple"
              label="Role"
              @validate="validateField('roles')"
              :error="errors.roles"
          />
        </div>
        <div class="col-md-6">
          <VInput
              input-type="password"
              id="password"
              v-model="form.password"
              label="Password"
              @validate="validateField('password')"
              :error="errors.password"
          />
        </div>
        <div class="col-md-6">
          <VInput
              input-type="password"
              id="password_confirmation"
              v-model="form.password_confirmation"
              label="Confirm Password"
              @validate="validateField('password_confirmation')"
              :error="errors.password_confirmation"
          />
        </div>
        <div class="col-12 text-end">
          <button @click="closeCreateModal" class="btn btn-danger" type="button">
            Close
          </button>
          <VButton :loading="isSubmitting"/>
        </div>
      </form>
    </template>
  </VModal>
</template>

<script setup>
import {onMounted, reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {array, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useUserStore} from "@/stores/admin/user-management/user";
import {useRoleStore} from "@/stores/admin/user-management/role";
import {storeToRefs} from "pinia";

const roleStore=useRoleStore();
const userStore=useUserStore();

const createModalOpened = defineModel('createModalOpened');

onMounted(()=>{
  roleStore.getRoles();
})

const {roles}=storeToRefs(roleStore);

const initialState = {
  name: '',
  phone: '',
  email: '',
  password: '',
  password_confirmation: '',
  roles: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
  name: string().required('Name is required.'),
  email: string().required('Email is required.').email(),
  phone: string().nullable(),
  password: string().required('Password is required'),
  password_confirmation: string().required('Confirm password is required'),
  roles: array().required('Roles is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeUser = async () => {
  let validated = await validateForm(validations, form)
  if (validated) {
    isSubmitting.value = true;
    try {
      let res = await userStore.storeUser(form);
      toast(res.status, res.data.message);
      closeCreateModal();
    } catch (e) {
      showErrors(e);
    } finally {
      isSubmitting.value = false;
    }
  }
}

const closeCreateModal = () => {
  resetForm();
  createModalOpened.value = false;
}

function resetForm() {
  Object.assign(form, {...initialState});
  errors.value = {};
}

</script>
