<template>

    <div :class="['sidebar', sidebarClass]" id="sidebar">
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

export default {
  components: {
    simplebar,
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
    this.initMouseoverListener();
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
    },
    initMouseoverListener() {
      document.addEventListener("mouseover", this.handleMouseover);
    },
    handleMouseover(e) {
      e.stopPropagation();

      const body = document.body;
      const toggleBtn = document.getElementById("toggle_btn");

      if (body.classList.contains("mini-sidebar") && this.isElementVisible(toggleBtn)) {
        const target = e.target.closest(".sidebar, .header-left");

        if (target) {
          body.classList.add("expand-menu");
          this.slideDownSubmenu();
        } else {
          body.classList.remove("expand-menu");
          this.slideUpSubmenu();
        }

        e.preventDefault();
      }
    },
    isElementVisible(element) {
      return element && (element.offsetWidth > 0 || element.offsetHeight > 0);
    },
    slideDownSubmenu() {
      // Force show all submenus when expanding in mini-sidebar mode
      const subdropElements = document.querySelectorAll(".subdrop");
      subdropElements.forEach((element) => {
        const submenu = element.nextElementSibling;
        if (submenu && submenu.tagName.toLowerCase() === "ul") {
          submenu.style.display = "block";
          submenu.classList.remove("d-none");
          submenu.classList.add("d-block");
        }
      });
    },
    slideUpSubmenu() {
      // Hide all submenus when collapsing in mini-sidebar mode
      const subdropElements = document.querySelectorAll(".subdrop");
      subdropElements.forEach((element) => {
        const submenu = element.nextElementSibling;
        if (submenu && submenu.tagName.toLowerCase() === "ul") {
          submenu.style.display = "none";
          submenu.classList.remove("d-block");
          submenu.classList.add("d-none");
        }
      });
    },
  },
  beforeUnmount() {
    document.removeEventListener("mouseover", this.handleMouseover);
  },
};
</script>
