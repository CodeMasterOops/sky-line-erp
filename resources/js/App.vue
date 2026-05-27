<template>
  <a-config-provider :theme="antdTheme">
    <div id="app">
      <div class="main-wrapper">
        <router-view />
      </div>
    </div>
  </a-config-provider>
</template>

<script setup>
import { nextTick, onMounted } from "vue";
import router from "@/router";
import { initBootstrapUi } from "@/helpers/initBootstrap";

/** Match resources/js/assets/scss/utils/_variables.scss */
const antdTheme = {
  token: {
    colorPrimary: "#1358f1",
    colorInfo: "#40abe6",
  },
};

const scheduleBootstrapUiInit = () => {
  nextTick(() => initBootstrapUi());
};

router.afterEach(() => {
  scheduleBootstrapUiInit();
});

onMounted(() => {
  const root = document.documentElement;

  // If no theme has been set yet, default to light
  if (!root.hasAttribute("data-theme")) {
    root.setAttribute("data-theme", "light");
  }

  scheduleBootstrapUiInit();
});
</script>
