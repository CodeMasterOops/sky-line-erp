<template>
  <div>
    <PageHeader
      title="Security"
      subtitle="Manage your password and account security"
    />
  </div>

  <div class="row">
    <div class="col-xl-12">
      <div class="settings-wrapper d-flex">
        <super-admin-settings-sidebar></super-admin-settings-sidebar>
        <div class="card flex-fill mb-0">
          <div class="card-header">
            <h4 class="fs-18 fw-bold">Security</h4>
          </div>
          <div class="card-body">
            <div
              class="d-flex align-items-center justify-content-between flex-wrap row-gap-3"
            >
              <div class="d-flex align-items-center">
                <span class="avatar avatar-lg border bg-light fs-24 me-2">
                  <i class="ti ti-eye-off text-gray-900 fs-18"></i>
                </span>
                <div>
                  <h5 class="fs-16 fw-medium mb-1">Password</h5>
                  <p class="fs-16 mb-0">
                    Update your password to keep your account secure
                  </p>
                </div>
              </div>
              <a
                href="javascript:void(0);"
                class="btn btn-primary"
                @click.prevent="changePasswordModalOpened = true"
              >
                Change Password
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <ChangePasswordModal
    v-model:change-password-modal-opened="changePasswordModalOpened"
  />
</template>

<script setup>
import { ref } from "vue";
import ChangePasswordModal from "./ChangePasswordModal.vue";

const changePasswordModalOpened = ref(false);
</script>
