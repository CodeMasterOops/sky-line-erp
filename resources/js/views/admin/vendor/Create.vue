<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Vendor</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Add New Vendor</h5>
        <router-link :to="{name:'admin.vendor-list'}">
          <button class="btn btn-sm btn-outline-primary">
            <i class="fa fa-list"> Vendor List</i>
          </button>
        </router-link>
      </div>
      <div class="card-body">
        <form @submit.prevent="storeVendor" class="g-3">
          <fieldset class="border rounded-1 border-primary p-2 mb-3">
            <legend class="text-primary">
              <strong>Vendor Information</strong>
            </legend>
            <div class="row g-3">
              <div class="col-md-4">
                <VInput
                    id="vendor_name"
                    v-model="form.vendor_name"
                    label="Vendor Name"
                    @validate="validateField('vendor_name')"
                    :error="errors.vendor_name"
                />
              </div>
              <div class="col-md-4">
                <VInput
                    id="slug"
                    v-model="form.slug"
                    label="Slug"
                    @validate="validateField('slug')"
                    :error="errors.slug"
                />
              </div>
              <div class="col-md-4">
                <VInput
                    id="phone"
                    v-model="form.phone"
                    label="Phone"
                    @validate="validateField('phone')"
                    :error="errors.phone"
                />
              </div>
              <div class="col-md-4">
                <VInput
                    id="email"
                    v-model="form.email"
                    label="Email"
                    @validate="validateField('email')"
                    :error="errors.email"
                />
              </div>
              <div class="col-md-4">
                <VInput
                    id="address"
                    v-model="form.address"
                    label="Address"
                    @validate="validateField('address')"
                    :error="errors.address"
                />
              </div>
            </div>
          </fieldset>
          <fieldset class="border rounded-1 border-primary p-2 mb-3">
            <legend class="text-primary">
              <strong>User Information</strong>
            </legend>
            <div class="row g-3">
              <div class="col-md-4">
                <VInput
                    id="user_name"
                    v-model="form.user_name"
                    label="User Name"
                    @validate="validateField('user_name')"
                    :error="errors.user_name"
                />
              </div>

              <div class="col-md-4">
                <VInput
                    id="user_phone"
                    v-model="form.user_phone"
                    label="User Phone"
                    @validate="validateField('user_phone')"
                    :error="errors.user_phone"
                />
              </div>

              <div class="col-md-4">
                <VInput
                    id="user_email"
                    v-model="form.user_email"
                    label="User Email"
                    @validate="validateField('user_email')"
                    :error="errors.user_email"
                />
              </div>
              <div class="col-md-4 mb-2">
                <VInput
                    input-type="password"
                    id="password"
                    v-model="form.password"
                    label="Password"
                    @validate="validateField('password')"
                    :error="errors.password"
                />
              </div>
              <div class="col-md-4 mb-2">
                <VInput
                    input-type="password"
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    label="Confirm Password"
                    @validate="validateField('password_confirmation')"
                    :error="errors.password_confirmation"
                />
              </div>
            </div>
          </fieldset>
          <div class="col-12 text-end">
            <VButton :loading="isSubmitting"/>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>
<script setup>
import {reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useVendorStore} from "@/stores/admin/vendor";
import {useRouter} from "vue-router";

const router = useRouter();
const vendorStore = useVendorStore();

const initialState = {
  vendor_name: '',
  slug: '',
  phone: '',
  email: '',
  address: '',
  user_name: '',
  user_phone: '',
  user_email: '',
  password: '',
  password_confirmation: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
  vendor_name: string().required('Vendor name is required.'),
  slug: string().required('Slug is required.'),
  phone: string().nullable(),
  email: string().required('Email is required.').email('Invalid email format'),
  user_name: string().required('User name is required.'),
  user_phone: string().nullable(),
  user_email: string().required('User email is required.').email('Invalid email format'),
  password: string().required('Password is required.'),
  password_confirmation: string().required('Confirm password is required.'),
  address: string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeVendor = async () => {
  let validated = await validateForm(validations, form)
  if (validated) {
    isSubmitting.value = true;
    try {
      let res = await vendorStore.storeVendor(form);
      toast(res.status, res.data.message);
      resetForm();
      await router.push({name: 'admin.vendor-list'});
    } catch (e) {
      showErrors(e);
    } finally {
      isSubmitting.value = false;
    }
  }
}

const resetForm = () => {
  Object.assign(form, {...initialState});
  errors.value = {};
}

</script>

