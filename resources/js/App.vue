<template>
  <div id="app">
    <div class="main-wrapper">
      <router-view />
    </div>
  </div>
</template>

<script setup>
import { onMounted } from "vue";

onMounted(() => {
  const root = document.documentElement;

  // If no theme has been set yet, default to light
  if (!root.hasAttribute("data-theme")) {
    root.setAttribute("data-theme", "light");
  }
});
</script>
