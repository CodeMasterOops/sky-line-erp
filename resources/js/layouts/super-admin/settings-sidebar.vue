<template>
  <div class="settings-sidebar" id="sidebar2">
    <div class="sidebar-inner slimscroll slimScrollDiv">
      <div id="sidebar-menu5" class="sidebar-menu">
        <h4 class="fw-bold fs-18 mb-2 pb-2">Settings</h4>
        <ul>
          <li class="submenu-open">
            <ul>
              <li class="submenu-open">
                <ul>
                  <li v-for="menu in Settings" :key="menu.title" class="submenu">
                    <a
                      href="javascript:void(0);"
                      @click="toggleSubMenu(menu)"
                      :class="{
                        subdrop: menu.expanded && isActiveMenu(menu),
                        active: isActiveMenu(menu),
                      }"
                    >
                      <vue-feather :type="menu.icon" class="fs-18"></vue-feather>
                      <span>{{ menu.title }}</span>
                      <span class="menu-arrow"></span>
                    </a>
                    <ul
                      :class="{
                        'd-block': menu.expanded,
                        'd-none': !menu.expanded,
                      }"
                    >
                      <template
                        v-for="subMenu in menu.subMenu"
                        :key="subMenu.routes || subMenu.title"
                      >
                        <li v-if="!subMenu.customSubmenuTwo">
                          <router-link
                            :to="subMenu.routes"
                            class="router-link"
                            :class="{ active: isActive(subMenu.routes) }"
                          >
                            {{ subMenu.title }}
                          </router-link>
                        </li>
                        <li v-else class="submenu submenu-two">
                          <a
                            href="javascript:void(0);"
                            @click="toggleSubMenuTwo(subMenu)"
                            :class="{
                              subdrop:
                                subMenu.expanded && isActiveSubMenu(subMenu),
                              active: isActiveSubMenu(subMenu),
                            }"
                          >
                            {{ subMenu.title }}
                            <span class="menu-arrow inside-submenu"></span>
                          </a>
                          <ul
                            :class="{
                              'd-block': subMenu.expanded,
                              'd-none': !subMenu.expanded,
                            }"
                          >
                            <li
                              v-for="subMenuTwo in subMenu.subMenusTwo"
                              :key="subMenuTwo.routes"
                            >
                              <router-link
                                :to="subMenuTwo.routes"
                                class="router-link"
                                :class="{
                                  active: isActive(subMenuTwo.routes),
                                }"
                              >
                                {{ subMenuTwo.title }}
                              </router-link>
                            </li>
                          </ul>
                        </li>
                      </template>
                    </ul>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import Settings from "@/assets/json/super-admin-settings.json";

export default {
  data() {
    return {
      Settings: Settings.map((menu) => ({
        ...menu,
        expanded: false,
        subMenu: menu.subMenu.map((subMenu) => ({
          ...subMenu,
          expanded: false,
        })),
      })),
    };
  },
  mounted() {
    this.initializeMenuState();
  },
  watch: {
    "$route.path": {
      handler() {
        this.initializeMenuState();
      },
      immediate: false,
    },
  },
  methods: {
    normalizePath(path) {
      if (!path) {
        return "";
      }
      let normalized = path.replace(/\/$/, "");
      normalized = normalized.split("?")[0].split("#")[0];
      return normalized;
    },
    isActive(route) {
      const currentPath = this.normalizePath(this.$route.path);
      const routePath = this.normalizePath(route);
      return currentPath === routePath;
    },
    isActiveMenu(menu) {
      if (!menu.subMenu || menu.subMenu.length === 0) {
        return false;
      }
      return menu.subMenu.some((subMenu) => {
        if (subMenu.customSubmenuTwo && subMenu.subMenusTwo) {
          return subMenu.subMenusTwo.some((subMenuTwo) =>
            this.isActive(subMenuTwo.routes),
          );
        }
        return this.isActive(subMenu.routes);
      });
    },
    isActiveSubMenu(subMenu) {
      if (!subMenu.subMenusTwo || subMenu.subMenusTwo.length === 0) {
        return false;
      }
      return subMenu.subMenusTwo.some((subMenuTwo) =>
        this.isActive(subMenuTwo.routes),
      );
    },
    toggleSubMenu(menu) {
      menu.expanded = !menu.expanded;
    },
    toggleSubMenuTwo(subMenu) {
      subMenu.expanded = !subMenu.expanded;
    },
    initializeMenuState() {
      this.Settings.forEach((menu) => {
        const hasActiveChild = this.isActiveMenu(menu);
        menu.expanded = hasActiveChild;

        if (menu.subMenu) {
          menu.subMenu.forEach((subMenu) => {
            if (subMenu.customSubmenuTwo) {
              const hasActiveNestedChild = this.isActiveSubMenu(subMenu);
              subMenu.expanded = hasActiveNestedChild;
            }
          });
        }
      });
    },
  },
};
</script>
