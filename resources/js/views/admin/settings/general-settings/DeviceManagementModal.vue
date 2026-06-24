<template>
  <VModal
    :show-modal="show"
    size="lg"
    title="Device Management"
    @close-click="close"
  >
    <template #modal-body>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="fs-14 text-muted mb-0">
          Devices currently signed in to your account.
        </p>
        <button
          type="button"
          class="btn btn-outline-danger btn-sm"
          :disabled="loading || devices.length < 2"
          @click="revokeOthers"
        >
          Sign out all other devices
        </button>
      </div>

      <div v-if="loading && !devices.length" class="py-3">
        <div
          v-for="n in 3"
          :key="n"
          class="placeholder-glow d-flex align-items-center gap-3 border-bottom py-2"
        >
          <span class="placeholder col-1 rounded" style="height: 32px" />
          <span class="placeholder col-6 rounded" />
          <span class="placeholder col-2 ms-auto rounded" />
        </div>
      </div>

      <div v-else-if="!devices.length" class="text-center text-muted py-5">
        <i class="ti ti-devices fs-1 d-block mb-2" />
        No active devices found.
      </div>

      <ul v-else class="list-unstyled mb-0">
        <li
          v-for="device in devices"
          :key="device.id"
          class="d-flex align-items-center gap-3 border-bottom py-2"
        >
          <span class="avatar avatar-md border bg-light flex-shrink-0">
            <i class="ti ti-device-desktop text-gray-900" />
          </span>
          <div class="min-w-0">
            <h6 class="fs-14 fw-medium mb-0">
              {{ device.device }}
              <span v-if="device.is_current" class="badge bg-outline-success ms-1">
                This device
              </span>
            </h6>
            <p class="fs-13 text-muted mb-0">
              <span v-if="device.ip_address">{{ device.ip_address }} · </span>
              Last active {{ formatDateTime(device.last_used_at) || "—" }}
            </p>
          </div>
          <button
            v-if="!device.is_current"
            type="button"
            class="btn btn-light btn-sm ms-auto text-danger"
            :disabled="revokingId === device.id"
            @click="revoke(device.id)"
          >
            <span
              v-if="revokingId === device.id"
              class="spinner-border spinner-border-sm me-1"
              role="status"
              aria-hidden="true"
            />
            Sign out
          </button>
        </li>
      </ul>
    </template>
  </VModal>
</template>

<script setup>
import { ref, watch } from "vue";
import { useSecurityStore } from "@/stores/admin/security";
import { useDisplayDate } from "@/composables/useDisplayDate";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";

const show = defineModel("show", { type: Boolean, default: false });

const securityStore = useSecurityStore();
const { formatDateTime } = useDisplayDate();

const devices = ref([]);
const loading = ref(false);
const revokingId = ref(null);

async function fetchDevices() {
  loading.value = true;
  try {
    const res = await securityStore.getDevices();
    devices.value = res.data.data;
  } catch (e) {
    showErrors(e);
  } finally {
    loading.value = false;
  }
}

async function revoke(id) {
  revokingId.value = id;
  try {
    const res = await securityStore.revokeDevice(id);
    toast(res.status, res.data.message);
    await fetchDevices();
  } catch (e) {
    showErrors(e);
  } finally {
    revokingId.value = null;
  }
}

async function revokeOthers() {
  loading.value = true;
  try {
    const res = await securityStore.revokeOtherDevices();
    toast(res.status, res.data.message);
    await fetchDevices();
  } catch (e) {
    showErrors(e);
  } finally {
    loading.value = false;
  }
}

watch(show, (open) => {
  if (open) {
    devices.value = [];
    fetchDevices();
  }
});

function close() {
  show.value = false;
}
</script>
