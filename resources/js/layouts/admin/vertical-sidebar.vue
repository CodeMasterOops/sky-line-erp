<template>
  <ul class="pt-2">
    <li
      class="submenu-open"
      v-for="(section, sectionIdx) in displaySections"
      :key="sectionKey(section, sectionIdx)"
    >
      <h6 class="submenu-hdr">{{ sectionTitle(section) }}</h6>
      <ul>
        <template v-for="(menu, menuIdx) in section.menu" :key="menu.menuValue + '-' + menuIdx">
          <li v-if="!menu.hasSubRoute && !menu.hasSubRouteTwo" :class="{ active: isMenuActive(menu) }">
            <router-link
              v-if="menu.route"
              :to="menu.route"
              active-class=""
              exact-active-class=""
              :class="{ active: isMenuActive(menu) }"
            >
              <i :class="menu.icon" class="fs-16 me-2"></i>
              <span>{{ menu.menuValue }}</span>
            </router-link>
          </li>
          <li v-else-if="menu.hasSubRoute" class="submenu">
            <a
              href="javascript:void(0);"
              @click="expandSubMenus(menu, section)"
              :class="{
                subdrop: isSubmenuDropped(menu, section),
                active: isActive(menu),
              }"
            >
              <i :class="menu.icon" class="fs-16 me-2"></i>
              <span>{{ menu.menuValue }}</span>
              <span class="menu-arrow"></span>
            </a>
            <ul :class="isSubmenuDropped(menu, section) || isActive(menu) ? 'd-block' : 'd-none'">
              <li v-for="(subMenu, index) in menu.subMenus" :key="index">
                <router-link
                  v-if="subMenu.route"
                  :to="subMenu.route"
                  active-class=""
                  exact-active-class=""
                  :class="{ active: isRouteTargetActive(subMenu.route) }"
                >{{ subMenu.menuValue }}</router-link>
              </li>
            </ul>
          </li>
          <li v-else-if="menu.hasSubRouteTwo" class="submenu">
            <a
              href="javascript:void(0);"
              @click="openMenu(menu, section)"
              :class="{
                subdrop: openMenuKey === menuKey(section, menu),
                active: isActive(menu),
              }"
            >
              <i :class="menu.icon" class="fs-16 me-2"></i>
              <span>{{ menu.menuValue }}</span>
              <span class="menu-arrow"></span>
            </a>
            <ul :class="openMenuKey === menuKey(section, menu) || isActive(menu) ? 'd-block' : 'd-none'">
              <li v-for="subMenus in menu.subMenus" :key="subMenus.menuValue">
                <template v-if="!subMenus.customSubmenuTwo">
                  <router-link
                    v-if="subMenus.route"
                    :to="subMenus.route"
                    active-class=""
                    exact-active-class=""
                    :class="{ active: isRouteTargetActive(subMenus.route) }"
                    >{{ subMenus.menuValue }}</router-link
                  >
                </template>
                <template v-else-if="subMenus.customSubmenuTwo">
                  <li class="submenu submenu-two">
                    <a
                      href="javascript:void(0);"
                      @click="openSubMenu(subMenus)"
                      :class="{
                        subdrop: openSubMenuKey === subMenus.menuValue,
                        active: isSubActive(subMenus),
                      }"
                    >
                      {{ subMenus.menuValue }}<span class="menu-arrow inside-submenu"></span>
                    </a>
                    <ul :class="openSubMenuKey === subMenus.menuValue || isSubActive(subMenus) ? 'd-block' : 'd-none'">
                      <li v-for="subMenuTwo in subMenus.subMenusTwo" :key="subMenuTwo.menuValue">
                        <router-link
                          v-if="subMenuTwo.route"
                          :to="subMenuTwo.route"
                          active-class=""
                          exact-active-class=""
                          :class="{ active: isRouteTargetActive(subMenuTwo.route) }"
                        >{{ subMenuTwo.menuValue }}</router-link>
                      </li>
                    </ul>
                  </li>
                </template>
              </li>
            </ul>
          </li>
        </template>
      </ul>
    </li>
  </ul>
</template>

<script>
import rawSidebar from "@/assets/json/sidebar.json";
import { hasPermission } from "@/helpers/checkPermission";
import { filterSidebarSections } from "@/helpers/sidebarMenu";

export default {
  data() {
    return {
      /** @type {Record<string, boolean>} */
      submenuExpanded: {},
      /** Key of the open hasSubRouteTwo module, e.g. "Modules::Inventory" */
      openMenuKey: null,
      /** menuValue of the open customSubmenuTwo group, e.g. "Operations" */
      openSubMenuKey: null,
    };
  },
  computed: {
    permissionFiltered() {
      return filterSidebarSections(rawSidebar, (p) => hasPermission(p));
    },
    displaySections() {
      return this.permissionFiltered;
    },
    isMenuActive() {
      return (menu) => {
        if (menu.active_link && this.$route.path === menu.active_link) {
          return true;
        }
        if (menu.route && this.isRouteTargetActive(menu.route)) {
          return true;
        }
        return false;
      };
    },
    isActive() {
      return (menu) => {
        if (menu.subMenus && Array.isArray(menu.subMenus)) {
          if (
            menu.subMenus.some((s) => {
              if (s.route && this.isRouteTargetActive(s.route)) return true;
              if (s.customSubmenuTwo && Array.isArray(s.subMenusTwo)) {
                return s.subMenusTwo.some((t) => t.route && this.isRouteTargetActive(t.route));
              }
              return false;
            })
          ) {
            return true;
          }
        }
        const parts = this.$route.path.split("/").filter(Boolean);
        const base = parts[0];
        return (
          base === menu.active_link ||
          base === menu.active_link1 ||
          base === menu.active_link2
        );
      };
    },
    isSubActive() {
      return (menu) => {
        if (menu.subMenusTwo && Array.isArray(menu.subMenusTwo)) {
          if (menu.subMenusTwo.some((t) => t.route && this.isRouteTargetActive(t.route))) {
            return true;
          }
        }
        const parts = this.$route.path.split("/").filter(Boolean);
        const base = parts[0];
        return base === menu.active_link || base === menu.active_link2;
      };
    },
  },
  watch: {
    "$route.fullPath"() {
      this.syncSubmenuFromRoute();
    },
    displaySections() {
      this.$nextTick(() => this.syncSubmenuFromRoute());
    },
  },
  mounted() {
    this.syncSubmenuFromRoute();
  },
  methods: {
    isRouteTargetActive(to) {
      if (!to) return false;
      if (to.name) {
        if (this.$route.name !== to.name) return false;
        if (to.query && typeof to.query === "object" && Object.keys(to.query).length) {
          for (const [k, v] of Object.entries(to.query)) {
            const cur = this.$route.query[k];
            const cur0 = Array.isArray(cur) ? cur[0] : cur;
            if (String(cur0 ?? "") !== String(v)) return false;
          }
        }
        return true;
      }
      return false;
    },
    sectionTitle(section) {
      return section.title ?? section.tittle ?? "";
    },
    sectionKey(section, idx) {
      return `${this.sectionTitle(section)}-${idx}`;
    },
    submenuKey(section, menu) {
      return `${this.sectionTitle(section)}::${menu.menuValue}`;
    },
    menuKey(section, menu) {
      return `${this.sectionTitle(section)}::${menu.menuValue}`;
    },
    isSubmenuDropped(menu, section) {
      const key = this.submenuKey(section, menu);
      return Object.prototype.hasOwnProperty.call(this.submenuExpanded, key)
        ? this.submenuExpanded[key]
        : false;
    },
    syncSubmenuFromRoute() {
      this.permissionFiltered.forEach((section) => {
        (section.menu || []).forEach((menu) => {
          if (menu.hasSubRoute && menu.subMenus) {
            const key = this.submenuKey(section, menu);
            const anyChildActive = menu.subMenus.some(
              (s) => s.route && this.isRouteTargetActive(s.route)
            );
            if (anyChildActive) {
              this.submenuExpanded = { ...this.submenuExpanded, [key]: true };
            }
          }
          if (menu.hasSubRouteTwo && menu.subMenus) {
            const key = this.menuKey(section, menu);
            const anyChildActive = menu.subMenus.some((s) => {
              if (s.route && this.isRouteTargetActive(s.route)) return true;
              if (s.customSubmenuTwo && Array.isArray(s.subMenusTwo)) {
                return s.subMenusTwo.some((t) => t.route && this.isRouteTargetActive(t.route));
              }
              return false;
            });
            if (anyChildActive) {
              this.openMenuKey = key;
              menu.subMenus.forEach((s) => {
                if (s.customSubmenuTwo && Array.isArray(s.subMenusTwo)) {
                  const groupActive = s.subMenusTwo.some((t) => t.route && this.isRouteTargetActive(t.route));
                  if (groupActive) {
                    this.openSubMenuKey = s.menuValue;
                  }
                }
              });
            }
          }
        });
      });
    },
    expandSubMenus(menu, section) {
      const isMiniSidebar = document.body.classList.contains("mini-sidebar");
      const key = this.submenuKey(section, menu);

      (section.menu || []).forEach((subMenu) => {
        if (subMenu.hasSubRoute && subMenu !== menu) {
          const otherKey = this.submenuKey(section, subMenu);
          this.submenuExpanded = { ...this.submenuExpanded, [otherKey]: false };
        }
      });

      const nextOpen = isMiniSidebar ? true : !this.isSubmenuDropped(menu, section);
      this.submenuExpanded = { ...this.submenuExpanded, [key]: nextOpen };
    },
    openMenu(menu, section) {
      const key = this.menuKey(section, menu);
      if (this.openMenuKey !== key) {
        this.openSubMenuKey = null;
      }
      this.openMenuKey = this.openMenuKey === key ? null : key;
    },
    openSubMenu(subMenus) {
      const key = subMenus.menuValue;
      this.openSubMenuKey = this.openSubMenuKey === key ? null : key;
    },
  },
};
</script>
