<template>
  <div class="page-header">
    <div class="add-item d-flex">
      <div class="page-title">
        <h4 class="fw-bold">{{ title }}</h4>
        <h6 v-if="subtitle">{{ subtitle }}</h6>
      </div>
    </div>
    <ul v-if="showToolbar" class="table-top-head">
      <li v-if="showExcelExport" v-can="'export_data'">
        <a
          href="javascript:void(0);"
          data-bs-toggle="tooltip"
          data-bs-placement="top"
          title="Export Excel"
          :class="{ 'opacity-50 pe-none': exporting }"
          @click.prevent="onExportClick"
        >
          <img src="@/assets/images/icons/excel.svg" alt="Export Excel">
        </a>
      </li>
      <li v-if="showRefresh">
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" @click="$emit('refresh')">
          <i class="ti ti-refresh"></i>
        </a>
      </li>
      <li v-if="showCollapse">
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header" @click="toggleHeader">
          <i class="ti ti-chevron-up"></i>
        </a>
      </li>
    </ul>
    <div class="page-btn d-flex align-items-center gap-2">
      <slot name="actions"></slot>
    </div>
  </div>
</template>

<script setup>
import { computed, useAttrs } from 'vue';
import { useRoute } from 'vue-router';
import { useQueuedExport } from '@/composables/useQueuedExport.js';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  /** When true, hides Refresh, Collapse, and Excel toolbar controls. */
  hideActionButtons: {
    type: Boolean,
    default: false,
  },
  /** Data-transfer entity type; when set, shows a working Excel export icon. */
  exportEntity: {
    type: String,
    default: null,
  },
  exportFormat: {
    type: String,
    default: 'csv',
  },
  /** Returns filter object for the queued export (e.g. current list filters). */
  getExportFilters: {
    type: Function,
    default: null,
  },
});

defineEmits(['refresh']);

const attrs = useAttrs();
const route = useRoute();
const { exporting, runExport } = useQueuedExport();

const isSuperAdminRoute = computed(() => Boolean(route.meta?.isSuperAdmin));

const hasRefreshHandler = computed(() => typeof attrs.onRefresh === 'function');

const showChrome = computed(
  () => !props.hideActionButtons && !isSuperAdminRoute.value,
);

const showExcelExport = computed(
  () => showChrome.value && Boolean(props.exportEntity),
);

const showCollapse = computed(() => showChrome.value);

const showRefresh = computed(() => {
  if (props.hideActionButtons) {
    return false;
  }

  if (isSuperAdminRoute.value) {
    return hasRefreshHandler.value;
  }

  return true;
});

const showToolbar = computed(
  () => showExcelExport.value || showRefresh.value || showCollapse.value,
);

const onExportClick = () => {
  if (!props.exportEntity) {
    return;
  }

  const getFilters = props.getExportFilters ?? (() => ({}));
  runExport(props.exportEntity, getFilters, props.exportFormat);
};

const toggleHeader = () => {
  const header = document.getElementById('collapse-header');
  if (header) {
    header.classList.toggle('active');
  }
  document.body.classList.toggle('header-collapse');
};
</script>
