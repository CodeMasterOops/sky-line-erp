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
          <li v-if="!menu.hasSubRoute" :class="{ active: isMenuActive(menu) }">
            <router-link v-if="menu.route" :to="menu.route">
              <i :class="menu.icon" class="fs-16 me-2"></i>
              <span>{{ menu.menuValue }}</span>
            </router-link>
          </li>
          <li v-else class="submenu">
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
                <router-link v-if="subMenu.route" :to="subMenu.route">{{ subMenu.menuValue }}</router-link>
              </li>
            </ul>
          </li>
        </template>
      </ul>
    </li>
  </ul>
</template>

<script>
import rawSidebar from "@/assets/json/super-admin-sidebar.json";
import { filterSidebarBySearch } from "@/helpers/sidebarMenu";

export default {
  data() {
    return {
      searchQuery: "",
      /** @type {Record<string, boolean>} */
      submenuExpanded: {},
    };
  },
  computed: {
    displaySections() {
      return filterSidebarBySearch(rawSidebar, this.searchQuery);
    },
    isMenuActive() {
      return (menu) => {
        const name = menu.route?.name;
        if (name && this.$route.name === name) return true;
        if (menu.active_link && this.$route.path === menu.active_link) return true;
        return false;
      };
    },
    isActive() {
      return (menu) => {
        if (menu.subMenus && Array.isArray(menu.subMenus)) {
          const childNames = menu.subMenus.map((s) => s.route && s.route.name).filter(Boolean);
          if (childNames.includes(this.$route.name)) {
            return true;
          }
        }
        if (menu.active_link) {
          return this.$route.path.startsWith(menu.active_link);
        }
        return false;
      };
    },
  },
  watch: {
    "$route.name"() {
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
      rawSidebar.forEach((section) => {
        (section.menu || []).forEach((menu) => {
          if (!menu.hasSubRoute || !menu.subMenus) return;
          const key = this.submenuKey(section, menu);
          const childNames = menu.subMenus.map((s) => s.route && s.route.name).filter(Boolean);
          if (childNames.includes(this.$route.name)) {
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
  },
};
</script>
