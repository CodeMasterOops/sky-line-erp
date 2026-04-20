<template>

    <div :class="['sidebar', 'admin-sidebar-shell', sidebarClass]" id="sidebar">
        <div class="sidebar-logo active">
            <router-link :to="{ name: 'admin.dashboard' }" class="logo logo-normal">
                <img src="@/assets/images/logo.svg" alt="Img" />
            </router-link>
            <router-link :to="{ name: 'admin.dashboard' }" class="logo logo-white">
                <img src="@/assets/images/logo-white.svg" alt="Img" />
            </router-link>
            <router-link :to="{ name: 'admin.dashboard' }" class="logo-small">
                <img src="@/assets/images/logo-small.png" alt="Img" />
            </router-link>
            <router-link :to="{ name: 'admin.dashboard' }" class="logo-small-white">
                <img src="@/assets/images/logo-small-white.png" alt="Img">
            </router-link>
            <a id="toggle_btn" href="javascript:void(0);" @click="toggleSidebar">
                <i class="fa fa-chevron-left"></i>
            </a>
        </div>

        <div class="admin-sidebar-shell__main">
            <simplebar id="scrollbar" class="admin-sidebar-shell__scroll" ref="scrollbar">
                <div class="sidebar-inner slimscroll flex-fill">
                    <div id="sidebar-menu" class="sidebar-menu">
                        <vertical-sidebar></vertical-sidebar>
                    </div>
                </div>
            </simplebar>
            <div class="admin-sidebar-shell__footer">
                <sidebar-trial-plan-card />
            </div>
        </div>
    </div>
</template>
<script>
import simplebar from "simplebar-vue";
import "simplebar-vue/dist/simplebar.min.css";
import SidebarTrialPlanCard from "@/components/layout/SidebarTrialPlanCard.vue";

export default {
  components: {
    simplebar,
    SidebarTrialPlanCard,
  },
  data() {
    return {
      sidebarClass: "",
    };
  },
  watch: {
    "$route.path"(newPath) {
      if (
        newPath.startsWith("/pos")
      ) {
        this.sidebarClass = "d-none";
      } else {
        this.sidebarClass = "";
      }
    },
  },
  mounted() {
    if (localStorage.getItem("admin_sidebar_mini") === "1") {
      document.body.classList.add("mini-sidebar");
    }
    document.body.classList.remove("expand-menu");
    // Run once when the component is mounted
    if (
      this.$route.path.startsWith("/pos")
    ) {
      this.sidebarClass = "d-none";
    }
  },
  methods: {
    toggleSidebar() {
      const body = document.body;
      body.classList.toggle("mini-sidebar");
      body.classList.remove("expand-menu");
      localStorage.setItem(
        "admin_sidebar_mini",
        body.classList.contains("mini-sidebar") ? "1" : "0"
      );
    },
  },
};
</script>

<style scoped>
.admin-sidebar-shell {
  display: flex !important;
  flex-direction: column;
  min-height: 0;
}

.admin-sidebar-shell__main {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.admin-sidebar-shell__scroll {
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
}

.admin-sidebar-shell__footer {
  flex-shrink: 0;
  padding: 0 12px 16px;
  margin-top: auto;
}
</style>
