<template>
  <form @submit.prevent="updateProfile">
    <div class="card-title-head">
      <h6 class="fs-16 fw-bold mb-3">
        <span class="fs-16 me-2"><i class="ti ti-user"></i></span>
        Basic information
      </h6>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="profile-pic-upload d-flex flex-wrap align-items-center">
          <div
            class="profile-pic p-2 position-relative d-flex align-items-center justify-content-center text-center image-field flex-shrink-0"
            style="width: 120px; height: 120px"
          >
            <img
              :src="previewSrc"
              class="object-fit-contain h-100 w-100 rounded-1 d-block p-1"
              alt="Profile"
            />
            <button
              v-if="showRemovePending"
              type="button"
              class="close rounded-1"
              aria-label="Remove selected image"
              @click.prevent="clearPendingImage"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="new-employee-field flex-grow-1 min-w-0">
            <label class="form-label fw-bold mb-1">Profile photo</label>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <input
                ref="fileInputRef"
                type="file"
                class="d-none"
                accept="image/jpeg,image/jpg,image/png"
                @change="onFileChange"
              />
              <button
                type="button"
                class="btn btn-primary btn-sm"
                @click="openFileDialog"
              >
                Change Image
              </button>
            </div>
            <p class="form-text fs-13 mt-2 mb-0">
              Upload an image below 2 MB, Accepted File format JPG, PNG
            </p>
            <div v-if="errors.profile_photo" class="text-danger small mt-1">
              {{ errors.profile_photo }}
            </div>
          </div>
        </div>
      </div>
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
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import { object, string } from "yup";
import { useYup } from "@/helpers/yup";
import { useProfileStore } from "@/stores/admin/profile";
import { storeToRefs } from "pinia";
import userIcon from "@/assets/images/user-icon.png";

const profileStore = useProfileStore();
const { profile } = storeToRefs(profileStore);

const fileInputRef = ref(null);
const filePreviewUrl = ref(null);
const isSubmitting = ref(false);

const initialState = {
  name: "",
  email: "",
  phone: "",
  profile_photo: "",
};

const form = reactive({ ...initialState });

const MAX_FILE_BYTES = 2 * 1024 * 1024;

const showRemovePending = computed(
  () => form.profile_photo instanceof File,
);

const previewSrc = computed(() => {
  if (form.profile_photo instanceof File && filePreviewUrl.value) {
    return filePreviewUrl.value;
  }
  return profile.value.data?.profile_photo_url || userIcon;
});

const validations = object({
  name: string().required("Name is required."),
  email: string()
    .required("Email is required.")
    .email("Invalid email address."),
  phone: string().nullable(),
});

const { errors, validateField, validateForm } = useYup(form, validations);

const openFileDialog = () => {
  fileInputRef.value?.click();
};

const revokeFilePreview = () => {
  if (filePreviewUrl.value) {
    URL.revokeObjectURL(filePreviewUrl.value);
    filePreviewUrl.value = null;
  }
};

const onFileChange = (e) => {
  const file = e.target?.files?.[0];
  errors.value = { ...errors.value, profile_photo: undefined };
  if (!file) {
    return;
  }
  if (!["image/jpeg", "image/jpg", "image/png"].includes(file.type)) {
    form.profile_photo = "";
    e.target.value = "";
    errors.value = {
      ...errors.value,
      profile_photo: "Please choose a JPG or PNG file.",
    };
    return;
  }
  if (file.size > MAX_FILE_BYTES) {
    form.profile_photo = "";
    e.target.value = "";
    errors.value = {
      ...errors.value,
      profile_photo: "Image must be 2 MB or smaller.",
    };
    return;
  }
  revokeFilePreview();
  filePreviewUrl.value = URL.createObjectURL(file);
  form.profile_photo = file;
};

const clearPendingImage = () => {
  form.profile_photo = "";
  if (fileInputRef.value) {
    fileInputRef.value.value = "";
  }
  revokeFilePreview();
};

const fillFormFromStore = () => {
  if (!profile.value.data) {
    return;
  }
  form.name = profile.value.data.name ?? "";
  form.email = profile.value.data.email ?? "";
  form.phone = profile.value.data.phone ?? "";
  form.profile_photo = "";
  revokeFilePreview();
  if (fileInputRef.value) {
    fileInputRef.value.value = "";
  }
};

const loadFromServer = async () => {
  await profileStore.getProfile();
  fillFormFromStore();
};

onMounted(() => {
  loadFromServer();
});

onBeforeUnmount(() => {
  revokeFilePreview();
});

const buildFormData = () => {
  const fd = new FormData();
  fd.append("name", form.name ?? "");
  fd.append("email", form.email ?? "");
  fd.append("phone", form.phone ?? "");
  if (form.profile_photo instanceof File) {
    fd.append("profile_photo", form.profile_photo);
  }
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
    clearPendingImage();
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
