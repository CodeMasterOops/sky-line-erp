<template>
  <div class="page-header">
    <div class="add-item d-flex">
      <div class="page-title">
        <h4 class="fw-bold">{{ title }}</h4>
        <h6 v-if="subtitle">{{ subtitle }}</h6>
      </div>
    </div>
    <ul class="table-top-head">
      <li>
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf">
          <img src="@/assets/images/icons/pdf.svg" alt="img">
        </a>
      </li>
      <li>
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel">
          <img src="@/assets/images/icons/excel.svg" alt="img">
        </a>
      </li>
      <li>
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" @click="$emit('refresh')">
          <i class="ti ti-refresh"></i>
        </a>
      </li>
      <li>
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
defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
});

defineEmits(['refresh']);

const toggleHeader = () => {
    const header = document.getElementById("collapse-header");
    if(header) header.classList.toggle("active");
    document.body.classList.toggle("header-collapse");
};
</script>
