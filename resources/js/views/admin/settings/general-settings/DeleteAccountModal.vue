<template>
  <VModal
    :show-modal="show"
    size="md"
    title="Delete Account"
    @close-click="close"
  >
    <template #modal-body>
      <div class="alert alert-danger d-flex gap-2" role="alert">
        <i class="ti ti-alert-octagon fs-18" />
        <span class="fs-13">
          This permanently deletes your account and signs you out. This action
          cannot be undone.
        </span>
      </div>

      <div class="input-blocks">
        <label class="fw-medium">
          Confirm your password <span class="text-danger">*</span>
        </label>
        <input
          v-model="password"
          type="password"
          class="form-control"
          :class="{ 'is-invalid': errors.password }"
          autocomplete="current-password"
        />
        <div v-if="errors.password" class="invalid-feedback d-block">
          {{ errors.password }}
        </div>
      </div>

      <div class="input-blocks mb-0">
        <label class="fw-medium">
          Type <span class="fw-bold">DELETE</span> to confirm
          <span class="text-danger">*</span>
        </label>
        <input
          v-model="confirmation"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.confirmation }"
          placeholder="DELETE"
        />
        <div v-if="errors.confirmation" class="invalid-feedback d-block">
          {{ errors.confirmation }}
        </div>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 mt-4">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="isSubmitting"
          @click="close"
        >
          Cancel
        </button>
        <button
          type="button"
          class="btn btn-danger"
          :disabled="isSubmitting || confirmation !== 'DELETE'"
          @click="submit"
        >
          <span
            v-if="isSubmitting"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"
          />
          Delete Account
        </button>
      </div>
    </template>
  </VModal>
</template>

<script setup>
import { reactive, ref, watch } from "vue";
import { useSecurityStore } from "@/stores/admin/security";
import { useAdminAuthStore } from "@/stores/admin/auth";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";

const show = defineModel("show", { type: Boolean, default: false });

const securityStore = useSecurityStore();
const authStore = useAdminAuthStore();

const password = ref("");
const confirmation = ref("");
const errors = reactive({ password: "", confirmation: "" });
const isSubmitting = ref(false);

async function submit() {
  errors.password = "";
  errors.confirmation = "";
  if (!password.value) {
    errors.password = "Password is required.";
    return;
  }
  isSubmitting.value = true;
  try {
    const res = await securityStore.deleteAccount({
      password: password.value,
      confirmation: confirmation.value,
    });
    toast(res.status, res.data.message);
    authStore.removeAuthToken();
    window.location.href = "/admin/login";
  } catch (e) {
    if (e.response?.status === 422) {
      const apiErrors = e.response.data?.errors ?? {};
      errors.password = apiErrors.password?.[0] ?? "";
      errors.confirmation = apiErrors.confirmation?.[0] ?? "";
      if (!errors.password && !errors.confirmation && e.response.data?.message) {
        errors.password = e.response.data.message;
      }
    } else {
      showErrors(e);
    }
  } finally {
    isSubmitting.value = false;
  }
}

watch(show, (open) => {
  if (!open) {
    password.value = "";
    confirmation.value = "";
    errors.password = "";
    errors.confirmation = "";
  }
});

function close() {
  show.value = false;
}
</script>
