<template>
  <form @submit.prevent="updateProfile">
    <div class="card-title-head">
      <h6 class="fs-16 fw-bold mb-3">
        <span class="fs-16 me-2"><i class="ti ti-user"></i></span>
        Basic information
      </h6>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="profile-name"
          >Name<span class="text-danger ms-1">*</span></label
        >
        <input
          id="profile-name"
          v-model="form.name"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.name }"
          @blur="validateField('name')"
        />
        <div v-if="errors.name" class="invalid-feedback d-block">
          {{ errors.name }}
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="profile-email"
          >Email<span class="text-danger ms-1">*</span></label
        >
        <input
          id="profile-email"
          v-model="form.email"
          type="email"
          class="form-control"
          :class="{ 'is-invalid': errors.email }"
          @blur="validateField('email')"
        />
        <div v-if="errors.email" class="invalid-feedback d-block">
          {{ errors.email }}
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="profile-phone">Phone Number</label>
        <input
          id="profile-phone"
          v-model="form.phone"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.phone }"
          @blur="validateField('phone')"
        />
        <div v-if="errors.phone" class="invalid-feedback d-block">
          {{ errors.phone }}
        </div>
      </div>
    </div>

    <div class="text-end settings-bottom-btn mt-4">
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="isSubmitting"
      >
        <span
          v-if="isSubmitting"
          class="spinner-border spinner-border-sm me-1"
          role="status"
          aria-hidden="true"
        />
        Save Changes
      </button>
    </div>
  </form>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import { object, string } from "yup";
import { useYup } from "@/helpers/yup";
import { useSuperAdminProfileStore } from "@/stores/super-admin/profile.js";
import { storeToRefs } from "pinia";

const profileStore = useSuperAdminProfileStore();
const { profile } = storeToRefs(profileStore);

const isSubmitting = ref(false);

const initialState = {
  name: "",
  email: "",
  phone: "",
};

const form = reactive({ ...initialState });

const validations = object({
  name: string().required("Name is required."),
  email: string()
    .required("Email is required.")
    .email("Invalid email address."),
  phone: string().nullable(),
});

const { errors, validateField, validateForm } = useYup(form, validations);

const fillFormFromStore = () => {
  if (!profile.value.data) {
    return;
  }
  form.name = profile.value.data.name ?? "";
  form.email = profile.value.data.email ?? "";
  form.phone = profile.value.data.phone ?? "";
};

const loadFromServer = async () => {
  await profileStore.getProfile(true);
  fillFormFromStore();
};

onMounted(() => {
  loadFromServer();
});

const buildFormData = () => {
  const fd = new FormData();
  fd.append("name", form.name ?? "");
  fd.append("email", form.email ?? "");
  fd.append("phone", form.phone ?? "");
  return fd;
};

const updateProfile = async () => {
  const ok = await validateForm();
  if (!ok) {
    return;
  }
  isSubmitting.value = true;
  try {
    const res = await profileStore.updateProfile(buildFormData());
    await loadFromServer();
    errors.value = {};
    toast(res.status, res.data.message);
  } catch (e) {
    showErrors(e);
  } finally {
    isSubmitting.value = false;
  }
};

defineExpose({ loadFromServer });
</script>
