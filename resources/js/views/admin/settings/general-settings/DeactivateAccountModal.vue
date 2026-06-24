<template>
  <VModal
    :show-modal="show"
    size="md"
    title="Deactivate Account"
    @close-click="close"
  >
    <template #modal-body>
      <div class="alert alert-warning d-flex gap-2" role="alert">
        <i class="ti ti-alert-triangle fs-18" />
        <span class="fs-13">
          Your account will be switched off and you will be signed out. An
          administrator can reactivate it for you later.
        </span>
      </div>

      <div class="input-blocks mb-0">
        <label class="fw-medium">
          Confirm your password <span class="text-danger">*</span>
        </label>
        <input
          v-model="password"
          type="password"
          class="form-control"
          :class="{ 'is-invalid': error }"
          autocomplete="current-password"
        />
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
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
          class="btn btn-warning"
          :disabled="isSubmitting"
          @click="submit"
        >
          <span
            v-if="isSubmitting"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"
          />
          Deactivate
        </button>
      </div>
    </template>
  </VModal>
</template>

<script setup>
import { ref, watch } from "vue";
import { useSecurityStore } from "@/stores/admin/security";
import { useAdminAuthStore } from "@/stores/admin/auth";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";

const show = defineModel("show", { type: Boolean, default: false });

const securityStore = useSecurityStore();
const authStore = useAdminAuthStore();

const password = ref("");
const error = ref("");
const isSubmitting = ref(false);

async function submit() {
  error.value = "";
  if (!password.value) {
    error.value = "Password is required.";
    return;
  }
  isSubmitting.value = true;
  try {
    const res = await securityStore.deactivateAccount({ password: password.value });
    toast(res.status, res.data.message);
    authStore.removeAuthToken();
    window.location.href = "/admin/login";
  } catch (e) {
    if (e.response?.status === 422) {
      error.value =
        e.response.data?.errors?.password?.[0] ?? e.response.data?.message ?? "";
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
    error.value = "";
  }
});

function close() {
  show.value = false;
}
</script>
