<template>
  <VModal
    :show-modal="changePasswordModalOpened"
    size="md"
    title="Change Password"
    @close-click="closeChangePasswordModal"
  >
    <template #modal-body>
      <div class="row">
        <div class="col-lg-12">
          <div class="input-blocks">
            <label class="fw-medium"
              >Current Password <span class="text-danger">*</span></label
            >
            <div class="pass-group">
              <input
                :type="showCurrentPassword ? 'text' : 'password'"
                v-model="form.current_password"
                class="pass-input form-control"
                :class="{ 'is-invalid': errors.current_password }"
                autocomplete="current-password"
                @blur="validateField('current_password')"
              />
              <span
                class="ti toggle-password"
                :class="
                  showCurrentPassword
                    ? 'ti-eye text-gray-9'
                    : 'ti-eye-off text-gray-9'
                "
                role="button"
                tabindex="0"
                @click="showCurrentPassword = !showCurrentPassword"
                @keydown.enter.prevent="
                  showCurrentPassword = !showCurrentPassword
                "
              />
            </div>
            <div
              v-if="errors.current_password"
              class="invalid-feedback d-block"
            >
              {{ errors.current_password }}
            </div>
          </div>
        </div>
        <div class="col-lg-12">
          <div class="input-blocks">
            <label class="fw-medium"
              >New Password <span class="text-danger">*</span></label
            >
            <div :class="passwordStrengthWrapperClass">
              <div class="pass-group" id="passwordInput">
                <input
                  v-model="form.password"
                  :type="showNewPassword ? 'text' : 'password'"
                  class="form-control settings-pass-inputs"
                  :class="{ 'is-invalid': errors.password }"
                  autocomplete="new-password"
                  @blur="validateField('password')"
                />
                <span
                  class="toggle-passwords ti"
                  :class="[
                    showNewPassword
                      ? 'ti-eye text-gray-9'
                      : 'ti-eye-off text-gray-9',
                  ]"
                  role="button"
                  tabindex="0"
                  @click="showNewPassword = !showNewPassword"
                  @keydown.enter.prevent="showNewPassword = !showNewPassword"
                />
                <span class="pass-checked"></span>
              </div>
              <div class="password-strength" id="passwordStrength">
                <span
                  id="poor"
                  :class="{ active: passwordStrengthBars[0] }"
                />
                <span
                  id="weak"
                  :class="{ active: passwordStrengthBars[1] }"
                />
                <span
                  id="strong"
                  :class="{ active: passwordStrengthBars[2] }"
                />
                <span
                  id="heavy"
                  :class="{ active: passwordStrengthBars[3] }"
                />
              </div>
              <div id="passwordInfo">{{ passwordStrengthInfo }}</div>
            </div>
            <div v-if="errors.password" class="text-danger small mt-1">
              {{ errors.password }}
            </div>
          </div>
        </div>
        <div class="col-lg-12">
          <div class="input-blocks mb-0">
            <label class="fw-medium"
              >Confirm Password <span class="text-danger">*</span></label
            >
            <div class="pass-group">
              <input
                v-model="form.password_confirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                class="form-control settings-pass-inputa"
                :class="{ 'is-invalid': errors.password_confirmation }"
                autocomplete="new-password"
                @blur="validateField('password_confirmation')"
              />
              <span
                class="toggle-passworda ti"
                :class="[
                  showConfirmPassword
                    ? 'ti-eye text-gray-9'
                    : 'ti-eye-off text-gray-9',
                ]"
                role="button"
                tabindex="0"
                @click="showConfirmPassword = !showConfirmPassword"
                @keydown.enter.prevent="
                  showConfirmPassword = !showConfirmPassword
                "
              />
            </div>
            <div
              v-if="errors.password_confirmation"
              class="text-danger small mt-1"
            >
              {{ errors.password_confirmation }}
            </div>
          </div>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="isSubmitting"
            @click="closeChangePasswordModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="isSubmitting"
            @click.prevent="submitChangePassword"
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
      </div>
    </template>
  </VModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { object, string, ref as yupRef } from "yup";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import { useYup } from "@/helpers/yup";
import { useProfileStore } from "@/stores/admin/profile";

const profileStore = useProfileStore();
const changePasswordModalOpened = defineModel("changePasswordModalOpened", {
  type: Boolean,
  default: false,
});

const initialForm = {
  current_password: "",
  password: "",
  password_confirmation: "",
};

const form = reactive({ ...initialForm });
const isSubmitting = ref(false);
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const validations = object({
  current_password: string().required("Current password is required."),
  password: string().required("New password is required."),
  password_confirmation: string()
    .required("Confirm password is required.")
    .oneOf([yupRef("password")], "Passwords must match"),
});

const { errors, validateField, validateForm } = useYup(form, validations);

function scorePassword(pwd) {
  if (!pwd) {
    return 0;
  }
  let n = 0;
  if (pwd.length >= 6) {
    n += 1;
  }
  if (pwd.length >= 8) {
    n += 1;
  }
  if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) {
    n += 1;
  }
  if (/\d/.test(pwd)) {
    n += 1;
  }
  if (/[^A-Za-z0-9]/.test(pwd)) {
    n += 1;
  }
  if (n <= 1) {
    return 1;
  }
  if (n === 2) {
    return 2;
  }
  if (n === 3) {
    return 3;
  }
  return 4;
}

const passwordStrengthLevel = computed(() => scorePassword(form.password));

const passwordStrengthWrapperClass = computed(() => {
  const lv = passwordStrengthLevel.value;
  if (!form.password) {
    return "";
  }
  if (lv === 1) {
    return "poor-active";
  }
  if (lv === 2) {
    return "avg-active";
  }
  if (lv === 3) {
    return "strong-active";
  }
  if (lv === 4) {
    return "heavy-active";
  }
  return "";
});

const passwordStrengthBars = computed(() => {
  const lv = passwordStrengthLevel.value;
  if (!form.password) {
    return [false, false, false, false];
  }
  return [lv >= 1, lv >= 2, lv >= 3, lv >= 4];
});

const passwordStrengthInfo = computed(() => {
  if (!form.password) {
    return "";
  }
  const lv = passwordStrengthLevel.value;
  if (lv === 1) {
    return "Use at least 6 characters and mix letters, numbers, and symbols.";
  }
  if (lv === 2) {
    return "Getting stronger. Add mixed case, numbers, or symbols.";
  }
  if (lv === 3) {
    return "Good password. You can add a symbol for maximum strength.";
  }
  return "Strong password.";
});

function resetChangePasswordForm() {
  showCurrentPassword.value = false;
  showNewPassword.value = false;
  showConfirmPassword.value = false;
  Object.assign(form, { ...initialForm });
  errors.value = {};
}

function closeChangePasswordModal() {
  changePasswordModalOpened.value = false;
}

watch(changePasswordModalOpened, (open) => {
  if (!open) {
    resetChangePasswordForm();
  }
});

async function submitChangePassword() {
  const ok = await validateForm();
  if (!ok) {
    return;
  }
  isSubmitting.value = true;
  try {
    const res = await profileStore.changePassword({ ...form });
    toast(res.status, res.data.message);
    changePasswordModalOpened.value = false;
  } catch (e) {
    showErrors(e);
  } finally {
    isSubmitting.value = false;
  }
}
</script>
