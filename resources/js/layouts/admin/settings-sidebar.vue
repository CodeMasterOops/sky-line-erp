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
                    <a href="javascript:void(0);" @click="toggleSubMenu(menu)"
                      :class="{ subdrop: menu.expanded && isActiveMenu(menu), active: isActiveMenu(menu) }">
                      <vue-feather :type="menu.icon" class="fs-18"></vue-feather>
                      <span>{{ menu.title }}</span>
                      <span class="menu-arrow"></span>
                    </a>
                    <ul :class="{ 'd-block': menu.expanded, 'd-none': !menu.expanded }">
                      <template v-for="subMenu in menu.subMenu" :key="subMenu.routes || subMenu.title">
                        <!-- Regular submenu item (no nested submenu) -->
                        <li v-if="!subMenu.customSubmenuTwo">
                          <router-link :to="subMenu.routes" class="router-link"
                            :class="{ active: isActive(subMenu.routes) }">
                            {{ subMenu.title }}
                          </router-link>
                        </li>
                        <!-- Nested submenu item (submenu-two) -->
                        <li v-else class="submenu submenu-two">
                          <a href="javascript:void(0);" @click="toggleSubMenuTwo(subMenu)" :class="{
                            subdrop: subMenu.expanded && isActiveSubMenu(subMenu),
                            active: isActiveSubMenu(subMenu)
                          }">
                            {{ subMenu.title }}
                            <span class="menu-arrow inside-submenu"></span>
                          </a>
                          <ul :class="{ 'd-block': subMenu.expanded, 'd-none': !subMenu.expanded }">
                            <li v-for="subMenuTwo in subMenu.subMenusTwo" :key="subMenuTwo.routes">
                              <router-link :to="subMenuTwo.routes" class="router-link"
                                :class="{ active: isActive(subMenuTwo.routes) }">
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
import Settings from "@/assets/json/settings.json";

export default {
  data() {
    return {
      Settings: Settings.map((menu) => ({
        ...menu,
        expanded: false, // Initialize expanded state
        subMenu: menu.subMenu.map((subMenu) => ({
          ...subMenu,
          expanded: false, // Initialize expanded state for nested submenus
        })),
      })),
    };
  },
  mounted() {
    // Initialize expanded state based on current route
    this.initializeMenuState();
  },
  watch: {
    // Watch for route changes to update menu state
    "$route.path": {
      handler() {
        this.initializeMenuState();
      },
      immediate: false,
    },
  },
  methods: {
    /**
     * Normalize path by removing trailing slashes and query params
     */
    normalizePath(path) {
      if (!path) return "";
      // Remove trailing slash
      let normalized = path.replace(/\/$/, "");
      // Remove query string and hash if present
      normalized = normalized.split("?")[0].split("#")[0];
      return normalized;
    },
    /**
     * Check if a route is active
     */
    isActive(route) {
      const currentPath = this.normalizePath(this.$route.path);
      const routePath = this.normalizePath(route);
      return currentPath === routePath;
    },
    /**
     * Check if a parent menu should be active (has active child)
     */
    isActiveMenu(menu) {
      if (!menu.subMenu || menu.subMenu.length === 0) {
        return false;
      }
      // Check if any submenu item matches the current route (including nested)
      return menu.subMenu.some((subMenu) => {
        if (subMenu.customSubmenuTwo && subMenu.subMenusTwo) {
          // Check nested submenu items
          return subMenu.subMenusTwo.some((subMenuTwo) =>
            this.isActive(subMenuTwo.routes)
          );
        }
        return this.isActive(subMenu.routes);
      });
    },
    /**
     * Check if a nested submenu should be active (has active child)
     */
    isActiveSubMenu(subMenu) {
      if (!subMenu.subMenusTwo || subMenu.subMenusTwo.length === 0) {
        return false;
      }
      // Check if any nested submenu item matches the current route
      return subMenu.subMenusTwo.some((subMenuTwo) =>
        this.isActive(subMenuTwo.routes)
      );
    },
    /**
     * Toggle submenu expansion
     */
    toggleSubMenu(menu) {
      menu.expanded = !menu.expanded;
    },
    /**
     * Toggle nested submenu expansion
     */
    toggleSubMenuTwo(subMenu) {
      subMenu.expanded = !subMenu.expanded;
    },
    /**
     * Initialize menu state based on current route
     * Expands menus that have active children
     */
    initializeMenuState() {
      this.Settings.forEach((menu) => {
        // Check if any child route is active (including nested)
        const hasActiveChild = this.isActiveMenu(menu);
        // Set expanded state (Vue 3 handles reactivity automatically)
        menu.expanded = hasActiveChild;

        // Initialize nested submenu states
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