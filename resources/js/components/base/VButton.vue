<template>
  <button :type="btnType" :class="btnClass" :disabled="disabled || loading">
    <span v-if="loading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
    {{ btnLabel }}
  </button>
</template>

<script setup>

defineProps({
  btnType: {
    type: String,
    default: "submit",
  },
  btnClass: {
    type: String,
    default: 'btn btn-primary'
  },
  btnLabel: {
    type: String,
    default: 'Save',
  },
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

</script>
