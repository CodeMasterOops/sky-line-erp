<template>
  <tr v-if="loaderType==='table'">
    <td :colspan="colspan" class="text-center">
      <div
          class="spinner-border text-primary"
          style="height: 40px; width: 40px"
          role="status"
      >
        <span class="visually-hidden">Loading...</span>
      </div>
    </td>
  </tr>
  <div v-else-if="loaderType==='spinner'" class="text-center">
    <div
        class="spinner-border text-primary"
        style="height: 60px; width: 60px"
        role="status"
    >
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>
  <div v-else-if="loaderType==='progress'" class="progress">
    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar"
         aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
    </div>
  </div>
</template>

<script setup>
defineProps({
  loaderType: {
    type: String,
    validator(value) {
      return ['table', 'progress', 'spinner'].includes(value)
    },
    default: 'table'
  },
  colspan: {
    type: Number,
  },
});
</script>
