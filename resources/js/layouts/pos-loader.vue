<template>
  <div>
    <div v-if="isLoading" id="global-loader">
      <div class="whirly-loader"></div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      isLoading: true,
    };
  },
  mounted() {
    setTimeout(() => {
      setTimeout(() => {
        this.isLoading = false;
      }, 100);
    }, 500);
  },
};
</script>
