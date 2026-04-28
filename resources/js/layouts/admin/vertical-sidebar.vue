<template>
  <div class="sidebar-menu-search pb-2 pt-0">
    <input
      type="search"
      class="form-control form-control-sm"
      placeholder="Search menu…"
      v-model="searchQuery"
      autocomplete="off"
      aria-label="Search menu"
      @keydown.stop
    />
  </div>
  <ul>
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
              @click="OpenMenu(menu)"
              :class="{
                subdrop: openMenuItem === menu,
                active: isActive(menu),
              }"
            >
              <i :class="menu.icon" class="fs-16 me-2"></i>
              <span>{{ menu.menuValue }}</span>
              <span class="menu-arrow"></span>
            </a>
            <ul :class="{ 'd-block': openMenuItem === menu || isActive(menu), 'd-none': openMenuItem !== menu && !isActive(menu) }">
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
                      @click="openSubmenuOne(subMenus)"
                      :class="{
                        subdrop: openSubmenuOneItem === subMenus,
                        active: isSubActive(subMenus),
                      }"
                    >
                      {{ subMenus.menuValue }}<span class="menu-arrow inside-submenu"></span>
                    </a>
                    <ul :class="{ 'd-block': openSubmenuOneItem === subMenus, 'd-none': openSubmenuOneItem !== subMenus }">
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
import { filterSidebarSections, filterSidebarBySearch } from "@/helpers/sidebarMenu";

export default {
  data() {
    return {
      searchQuery: "",
      /** @type {Record<string, boolean>} */
      submenuExpanded: {},
      openMenuItem: null,
      openSubmenuOneItem: null,
    };
  },
  computed: {
    permissionFiltered() {
      return filterSidebarSections(rawSidebar, (p) => hasPermission(p));
    },
    displaySections() {
      return filterSidebarBySearch(this.permissionFiltered, this.searchQuery);
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
            menu.subMenus.some(
              (s) => s.route && this.isRouteTargetActive(s.route)
            )
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
    /**
     * True when the current route matches a sidebar link target, including
     * `query` (e.g. same route name, different `?type=` for customers vs suppliers).
     */
    isRouteTargetActive(to) {
      if (!to) {
        return false;
      }
      if (to.name) {
        if (this.$route.name !== to.name) {
          return false;
        }
        if (to.query && typeof to.query === "object" && Object.keys(to.query).length) {
          for (const [k, v] of Object.entries(to.query)) {
            const cur = this.$route.query[k];
            const cur0 = Array.isArray(cur) ? cur[0] : cur;
            if (String(cur0 ?? "") !== String(v)) {
              return false;
            }
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
    isSubmenuDropped(menu, section) {
      const key = this.submenuKey(section, menu);
      if (Object.prototype.hasOwnProperty.call(this.submenuExpanded, key)) {
        return this.submenuExpanded[key];
      }
      return false;
    },
    syncSubmenuFromRoute() {
      this.permissionFiltered.forEach((section) => {
        (section.menu || []).forEach((menu) => {
          if (!menu.hasSubRoute || !menu.subMenus) return;
          const key = this.submenuKey(section, menu);
          const anyChildActive = menu.subMenus.some(
            (s) => s.route && this.isRouteTargetActive(s.route)
          );
          if (anyChildActive) {
            this.submenuExpanded = { ...this.submenuExpanded, [key]: true };
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
    OpenMenu(menu) {
      const isMiniSidebar = document.body.classList.contains("mini-sidebar");

      if (this.openMenuItem !== menu) {
        this.openSubmenuOneItem = null;
      }

      if (isMiniSidebar) {
        this.openMenuItem = menu;
      } else {
        this.openMenuItem = this.openMenuItem === menu ? null : menu;
      }
    },
    openSubmenuOne(subMenus) {
      const isMiniSidebar = document.body.classList.contains("mini-sidebar");
      if (isMiniSidebar) {
        this.openSubmenuOneItem = subMenus;
      } else {
        this.openSubmenuOneItem = this.openSubmenuOneItem === subMenus ? null : subMenus;
      }
    },
  },
};
</script>
