<template>

    <div :class="['sidebar', sidebarClass]" id="sidebar">
        <div class="sidebar-logo active">
            <router-link :to="{ name: 'super-admin.dashboard' }" class="logo logo-normal">
                <img src="@/assets/images/logo.svg" alt="Img" />
            </router-link>
            <router-link :to="{ name: 'super-admin.dashboard' }" class="logo logo-white">
                <img src="@/assets/images/logo-white.svg" alt="Img" />
            </router-link>
            <router-link :to="{ name: 'super-admin.dashboard' }" class="logo-small">
                <img src="@/assets/images/logo-small.png" alt="Img" />
            </router-link>
            <router-link :to="{ name: 'super-admin.dashboard' }" class="logo-small-white">
                <img src="@/assets/images/logo-small-white.png" alt="Img">
            </router-link>
            <a id="toggle_btn" href="javascript:void(0);" @click="toggleSidebar">
                <i class="fa fa-chevron-left"></i>
            </a>
        </div>

        <simplebar id="scrollbar" class="h-100" ref="scrollbar">
            <div class="sidebar-inner slimscroll flex-fill">
                <div id="sidebar-menu" class="sidebar-menu">
                    <vertical-sidebar></vertical-sidebar>
                </div>
            </div>
        </simplebar>
    </div>
</template>
<script>
import simplebar from "simplebar-vue";
import "simplebar-vue/dist/simplebar.min.css";
import VerticalSidebar from "./vertical-sidebar.vue";

export default {
  components: {
    simplebar,
    VerticalSidebar,
  },
  data() {
    return {
      sidebarClass: "",
    };
  },
  methods: {
    toggleSidebar() {
      const body = document.body;
      body.classList.toggle("mini-sidebar");
      body.classList.remove("expand-menu");
      localStorage.setItem(
        "super_admin_sidebar_mini",
        body.classList.contains("mini-sidebar") ? "1" : "0"
      );
    },
  },
  mounted() {
    if (localStorage.getItem("super_admin_sidebar_mini") === "1") {
      document.body.classList.add("mini-sidebar");
    }
    document.body.classList.remove("expand-menu");
  },
};
</script>
