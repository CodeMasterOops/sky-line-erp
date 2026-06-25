<template>
  <div>
    <PageHeader
      title="General Settings"
      subtitle="Manage your general settings"
    />
  </div>

  <div class="row">
    <div class="col-xl-12">
      <div class="settings-wrapper d-flex">
        <settings-sidebar></settings-sidebar>
        <div class="card flex-fill mb-0">
          <div class="card-header">
            <h4 class="fs-18 fw-bold">Security</h4>
          </div>
          <div class="card-body">
            <div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-eye-off text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">Password</h5>
                    <p class="fs-16">Keep your password secure and up to date</p>
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
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-shield text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">
                      Two Factor Authentication
                    </h5>
                    <p class="fs-16">
                      Receive codes via SMS or email every time you login
                    </p>
                  </div>
                </div>
                <div
                  class="status-toggle modal-status d-flex justify-content-between align-items-center ms-2"
                >
                  <input type="checkbox" id="user3" class="check" checked />
                  <label for="user3" class="checktoggle"> </label>
                </div>
              </div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-brand-google text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">Google Authentication</h5>
                    <p class="fs-16">Connect to Google</p>
                  </div>
                </div>
                <div class="d-flex align-items-center">
                  <span class="badge bg-outline-success">Connected</span>
                  <div
                    class="status-toggle modal-status d-flex justify-content-between align-items-center ms-3"
                  >
                    <input type="checkbox" id="user4" class="check" checked />
                    <label for="user4" class="checktoggle"> </label>
                  </div>
                </div>
              </div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-phone text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">
                      Phone Number Verification
                    </h5>
                    <p class="fs-16">Verified Mobile Number : +81699799974</p>
                  </div>
                </div>
                <div class="d-flex align-items-center">
                  <span class="fs-20 text-success me-3">
                    <i class="ti ti-circle-check-filled"></i>
                  </span>
                  <a
                    href="javascript:void(0);"
                    class="btn btn-primary mt-0"
                    @click.prevent="phoneVerificationModalOpened = true"
                    >Change</a
                  >
                  <a
                    href="javascript:void(0);"
                    class="btn btn-secondary ms-3"
                    >Remove</a
                  >
                </div>
              </div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-mail text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">Email Verification</h5>
                    <p class="fs-16">Verified Email : info@example.com</p>
                  </div>
                </div>
                <div class="d-flex align-items-center">
                  <span class="fs-20 text-success me-3">
                    <i class="ti ti-circle-check-filled"></i>
                  </span>
                  <a
                    href="javascript:void(0);"
                    class="btn btn-primary mt-0"
                    @click.prevent="emailVerificationModalOpened = true"
                    >Change</a
                  >
                  <a
                    href="javascript:void(0);"
                    class="btn btn-secondary ms-3"
                    >Remove</a
                  >
                </div>
              </div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-tool text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">Device Management</h5>
                    <p class="fs-16">
                      Manage devices associated with the account
                    </p>
                  </div>
                </div>
                <a
                  href="javascript:void(0);"
                  class="btn btn-primary mt-0"
                  @click.prevent="deviceModalOpened = true"
                  >Manage</a
                >
              </div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-activity text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">Account Activity</h5>
                    <p class="fs-16">
                      Manage activities associated with the account
                    </p>
                  </div>
                </div>
                <a
                  href="javascript:void(0);"
                  class="btn btn-primary mt-0"
                  @click.prevent="activityModalOpened = true"
                  >View</a
                >
              </div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3 border-bottom mb-3 pb-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-ban text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">Deactivate Account</h5>
                    <p class="fs-16">
                      This switches off your account and signs you out. An
                      administrator can reactivate it for you later.
                    </p>
                  </div>
                </div>
                <a
                  href="javascript:void(0);"
                  class="btn btn-primary mt-0"
                  @click.prevent="deactivateModalOpened = true"
                  >Deactivate</a
                >
              </div>
              <div
                class="d-flex align-items-center justify-content-between flex-wrap row-gap-3"
              >
                <div class="d-flex align-items-center">
                  <span class="avatar avatar-lg border bg-light fs-24 me-2">
                    <i class="ti ti-trash text-gray-900 fs-18"></i>
                  </span>
                  <div>
                    <h5 class="fs-16 fw-medium mb-1">Delete Account</h5>
                    <p class="fs-16">Your account will be permanently deleted</p>
                  </div>
                </div>
                <a
                  href="javascript:void(0);"
                  class="btn btn-danger"
                  @click.prevent="deleteModalOpened = true"
                  >Delete</a
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <ChangePasswordModal
    v-model:change-password-modal-opened="changePasswordModalOpened"
  />
  <DeviceManagementModal v-model:show="deviceModalOpened" />
  <AccountActivityModal v-model:show="activityModalOpened" />
  <DeactivateAccountModal v-model:show="deactivateModalOpened" />
  <DeleteAccountModal v-model:show="deleteModalOpened" />

  <VModal
    :show-modal="phoneVerificationModalOpened"
    size="md"
    title="Phone Number Verification"
    @close-click="phoneVerificationModalOpened = false"
  >
    <template #modal-body>
      <p class="text-muted mb-3">Enter your new phone number to update your verified mobile number.</p>
      <div class="mb-3">
        <label class="form-label fw-medium">New Phone Number <span class="text-danger">*</span></label>
        <input type="tel" class="form-control" placeholder="+977 98XXXXXXXX" />
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="button" class="btn btn-secondary" @click="phoneVerificationModalOpened = false">Cancel</button>
        <button type="button" class="btn btn-primary">Send OTP</button>
      </div>
    </template>
  </VModal>

  <VModal
    :show-modal="emailVerificationModalOpened"
    size="md"
    title="Email Verification"
    @close-click="emailVerificationModalOpened = false"
  >
    <template #modal-body>
      <p class="text-muted mb-3">Enter your new email address to update your verified email.</p>
      <div class="mb-3">
        <label class="form-label fw-medium">New Email Address <span class="text-danger">*</span></label>
        <input type="email" class="form-control" placeholder="you@example.com" />
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="button" class="btn btn-secondary" @click="emailVerificationModalOpened = false">Cancel</button>
        <button type="button" class="btn btn-primary">Send Verification Link</button>
      </div>
    </template>
  </VModal>
</template>

<script setup>
import { ref } from "vue";
import ChangePasswordModal from "./ChangePasswordModal.vue";
import DeviceManagementModal from "./DeviceManagementModal.vue";
import AccountActivityModal from "./AccountActivityModal.vue";
import DeactivateAccountModal from "./DeactivateAccountModal.vue";
import DeleteAccountModal from "./DeleteAccountModal.vue";

const changePasswordModalOpened = ref(false);
const deviceModalOpened = ref(false);
const activityModalOpened = ref(false);
const deactivateModalOpened = ref(false);
const deleteModalOpened = ref(false);
const phoneVerificationModalOpened = ref(false);
const emailVerificationModalOpened = ref(false);
</script>
