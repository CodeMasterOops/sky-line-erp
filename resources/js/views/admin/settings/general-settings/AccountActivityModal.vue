<template>
  <VModal
    :show-modal="show"
    size="lg"
    title="Account Activity"
    @close-click="close"
  >
    <template #modal-body>
      <div v-if="loading && !activities.length" class="py-3">
        <div
          v-for="n in 4"
          :key="n"
          class="placeholder-glow d-flex align-items-center gap-3 border-bottom py-2"
        >
          <span class="placeholder col-1 rounded" style="height: 32px" />
          <span class="placeholder col-6 rounded" />
          <span class="placeholder col-3 ms-auto rounded" />
        </div>
      </div>

      <div v-else-if="!activities.length" class="text-center text-muted py-5">
        <i class="ti ti-activity fs-1 d-block mb-2" />
        No account activity yet.
      </div>

      <ul v-else class="list-unstyled mb-0">
        <li
          v-for="item in activities"
          :key="item.id"
          class="d-flex align-items-center gap-3 border-bottom py-2"
        >
          <span class="avatar avatar-md border bg-light flex-shrink-0">
            <i class="ti text-gray-900" :class="eventIcon(item.event)" />
          </span>
          <div class="min-w-0">
            <h6 class="fs-14 fw-medium mb-0">{{ item.label }}</h6>
            <p class="fs-13 text-muted mb-0">
              {{ item.device }}<span v-if="item.ip_address"> · {{ item.ip_address }}</span>
            </p>
          </div>
          <span class="fs-13 text-muted ms-auto text-nowrap">
            {{ formatDateTime(item.created_at) }}
          </span>
        </li>
      </ul>

      <div v-if="hasMore" class="text-center mt-3">
        <button
          type="button"
          class="btn btn-light btn-sm"
          :disabled="loading"
          @click="loadMore"
        >
          <span
            v-if="loading"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"
          />
          Load more
        </button>
      </div>
    </template>
  </VModal>
</template>

<script setup>
import { ref, watch } from "vue";
import { useSecurityStore } from "@/stores/admin/security";
import { useDisplayDate } from "@/composables/useDisplayDate";
import showErrors from "@/helpers/showErrors";

const show = defineModel("show", { type: Boolean, default: false });

const securityStore = useSecurityStore();
const { formatDateTime } = useDisplayDate();

const activities = ref([]);
const loading = ref(false);
const currentPage = ref(0);
const lastPage = ref(1);

const hasMore = ref(false);

const eventIcon = (event) =>
  ({
    login: "ti-login",
    logout: "ti-logout",
    password_changed: "ti-key",
    deactivated: "ti-ban",
    account_deleted: "ti-trash",
    device_revoked: "ti-device-desktop-off",
  })[event] ?? "ti-activity";

async function fetchPage(page) {
  loading.value = true;
  try {
    const res = await securityStore.getActivity(page);
    const payload = res.data;
    activities.value =
      page === 1 ? payload.data : [...activities.value, ...payload.data];
    currentPage.value = payload.meta?.current_page ?? page;
    lastPage.value = payload.meta?.last_page ?? page;
    hasMore.value = currentPage.value < lastPage.value;
  } catch (e) {
    showErrors(e);
  } finally {
    loading.value = false;
  }
}

function loadMore() {
  fetchPage(currentPage.value + 1);
}

watch(show, (open) => {
  if (open) {
    activities.value = [];
    currentPage.value = 0;
    fetchPage(1);
  }
});

function close() {
  show.value = false;
}
</script>
