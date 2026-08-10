<template>
  <ul class="pt-2">
    <li class="submenu-open sidebar-pinned">
      <h6 class="submenu-hdr d-flex align-items-center justify-content-between">
        <span>Pinned</span>
        <a
          href="javascript:void(0);"
          class="sidebar-pinned__manage"
          title="Manage pinned links"
          @click="openManage"
        >
          <i class="ti ti-pencil"></i>
        </a>
      </h6>
      <ul v-if="pinnedLeaves.length">
        <li
          v-for="leaf in pinnedLeaves"
          :key="leaf.routeName"
          :class="{ active: isLeafActive(leaf) }"
        >
          <router-link
            :to="{ name: leaf.routeName }"
            active-class=""
            exact-active-class=""
            :class="{ active: isLeafActive(leaf) }"
          >
            <i :class="leaf.icon" class="fs-16 me-2"></i>
            <span>{{ leaf.label }}</span>
          </router-link>
        </li>
      </ul>
      <ul v-else>
        <li>
          <a href="javascript:void(0);" class="sidebar-pinned__empty" @click="openManage">
            <i class="ti ti-pin fs-16 me-2"></i>
            <span>Pin shortcuts</span>
          </a>
        </li>
      </ul>
    </li>
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
                  :class="{ active: isSubmenuItemActive(subMenu) }"
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
                    :class="{ active: isSubmenuItemActive(subMenus) }"
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
                          :class="{ active: isSubmenuItemActive(subMenuTwo) }"
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

  <VModal
    :show-modal="showManageModal"
    size="lg"
    title="Manage Sidebar Links"
    @close-click="closeManage"
  >
    <template #modal-body>
      <p class="text-muted fs-13 mb-3">
        Pick up to {{ maxPins }} pages to pin to the sidebar.
        <span class="fw-semibold">{{ draftLinks.length }} / {{ maxPins }}</span> selected.
      </p>
      <div class="quick-links-editor">
        <div
          v-for="group in groupedLeaves"
          :key="group.category"
          class="quick-links-editor__group"
        >
          <h6 class="quick-links-editor__heading">{{ group.category }}</h6>
          <div class="row g-2">
            <div v-for="leaf in group.items" :key="leaf.routeName" class="col-md-6">
              <label
                class="quick-links-editor__item"
                :class="{
                  'quick-links-editor__item--active': isDrafted(leaf.routeName),
                  'quick-links-editor__item--disabled': !isDrafted(leaf.routeName) && limitReached,
                }"
              >
                <input
                  type="checkbox"
                  class="form-check-input m-0 flex-shrink-0"
                  :checked="isDrafted(leaf.routeName)"
                  :disabled="!isDrafted(leaf.routeName) && limitReached"
                  @change="toggleDraft(leaf.routeName)"
                />
                <i :class="leaf.icon" class="mx-2 fs-16"></i>
                <span class="text-truncate">{{ leaf.label }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-light" @click="closeManage">Cancel</button>
        <button type="button" class="btn btn-primary" :disabled="savingPins" @click="savePins">
          <span v-if="savingPins" class="spinner-border spinner-border-sm me-1"></span>
          Save
        </button>
      </div>
    </template>
  </VModal>
</template>

<script>
import rawSidebar from "@/assets/json/sidebar.json";
import { hasPermission } from "@/helpers/checkPermission";
import { isModuleEnabled } from "@/helpers/checkModule";
import { filterSidebarSections, flattenMenuLeaves } from "@/helpers/sidebarMenu";
import { useSidebarPinnedLinksStore } from "@/stores/admin/sidebarPinnedLinks";
import { MAX_SIDEBAR_PINNED_LINKS } from "@/constants/sidebarPins";
import VModal from "@/components/base/VModal.vue";
import { toast } from "@/helpers/toast.js";
import showErrors from "@/helpers/showErrors";

export default {
  components: {
    VModal,
  },
  setup() {
    return { pinnedStore: useSidebarPinnedLinksStore() };
  },
  data() {
    return {
      /** @type {Record<string, boolean>} */
      submenuExpanded: {},
      /** Key of the open hasSubRouteTwo module, e.g. "Modules::Inventory" */
      openMenuKey: null,
      /** menuValue of the open customSubmenuTwo group, e.g. "Operations" */
      openSubMenuKey: null,
      maxPins: MAX_SIDEBAR_PINNED_LINKS,
      showManageModal: false,
      savingPins: false,
      /** @type {string[]} Working selection while the manage modal is open */
      draftLinks: [],
    };
  },
  computed: {
    permissionFiltered() {
      return filterSidebarSections(
        rawSidebar,
        (p) => hasPermission(p),
        (m) => isModuleEnabled(m),
      );
    },
    displaySections() {
      return this.permissionFiltered;
    },
    availableLeaves() {
      return flattenMenuLeaves(this.permissionFiltered).filter(
        (leaf) => leaf.routeName !== "admin.dashboard"
      );
    },
    leafIndex() {
      const index = {};
      this.availableLeaves.forEach((leaf) => {
        index[leaf.routeName] = leaf;
      });
      return index;
    },
    pinnedLeaves() {
      return this.pinnedStore.links
        .map((name) => this.leafIndex[name])
        .filter(Boolean);
    },
    groupedLeaves() {
      const groups = [];
      const byCategory = {};
      this.availableLeaves.forEach((leaf) => {
        if (!byCategory[leaf.category]) {
          byCategory[leaf.category] = { category: leaf.category, items: [] };
          groups.push(byCategory[leaf.category]);
        }
        byCategory[leaf.category].items.push(leaf);
      });
      return groups;
    },
    limitReached() {
      return this.draftLinks.length >= this.maxPins;
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
              if (this.isSubmenuItemActive(s)) return true;
              if (s.customSubmenuTwo && Array.isArray(s.subMenusTwo)) {
                return s.subMenusTwo.some((t) => this.isSubmenuItemActive(t));
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
          if (menu.subMenusTwo.some((t) => this.isSubmenuItemActive(t))) {
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
    isSubmenuItemActive(subMenu) {
      if (subMenu.active_routes && subMenu.active_routes.includes(this.$route.name)) {
        return true;
      }
      return subMenu.route ? this.isRouteTargetActive(subMenu.route) : false;
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
            const anyChildActive = menu.subMenus.some((s) => this.isSubmenuItemActive(s));
            this.submenuExpanded = { ...this.submenuExpanded, [key]: anyChildActive };
          }
          if (menu.hasSubRouteTwo && menu.subMenus) {
            const key = this.menuKey(section, menu);
            const anyChildActive = menu.subMenus.some((s) => {
              if (this.isSubmenuItemActive(s)) return true;
              if (s.customSubmenuTwo && Array.isArray(s.subMenusTwo)) {
                return s.subMenusTwo.some((t) => this.isSubmenuItemActive(t));
              }
              return false;
            });
            if (anyChildActive) {
              this.openMenuKey = key;
              menu.subMenus.forEach((s) => {
                if (s.customSubmenuTwo && Array.isArray(s.subMenusTwo)) {
                  const groupActive = s.subMenusTwo.some((t) => this.isSubmenuItemActive(t));
                  if (groupActive) {
                    this.openSubMenuKey = s.menuValue;
                  }
                }
              });
            } else if (this.openMenuKey === key) {
              this.openMenuKey = null;
              this.openSubMenuKey = null;
            }
          }
        });
      });
    },
    expandSubMenus(menu, section) {
      const isMiniSidebar = document.body.classList.contains("mini-sidebar");
      const key = this.submenuKey(section, menu);

      // Close all hasSubRoute items across every section
      const collapsed = {};
      this.permissionFiltered.forEach((sec) => {
        (sec.menu || []).forEach((subMenu) => {
          if (subMenu.hasSubRoute) {
            collapsed[this.submenuKey(sec, subMenu)] = false;
          }
        });
      });

      // Close any open hasSubRouteTwo menu
      this.openMenuKey = null;
      this.openSubMenuKey = null;

      const nextOpen = isMiniSidebar ? true : !this.isSubmenuDropped(menu, section);
      this.submenuExpanded = { ...collapsed, [key]: nextOpen };
    },
    openMenu(menu, section) {
      const key = this.menuKey(section, menu);
      const isOpening = this.openMenuKey !== key;

      // Close all hasSubRoute items when opening a hasSubRouteTwo menu
      if (isOpening) {
        const collapsed = {};
        this.permissionFiltered.forEach((sec) => {
          (sec.menu || []).forEach((subMenu) => {
            if (subMenu.hasSubRoute) {
              collapsed[this.submenuKey(sec, subMenu)] = false;
            }
          });
        });
        this.submenuExpanded = collapsed;
        this.openSubMenuKey = null;
      }

      this.openMenuKey = isOpening ? key : null;
      if (!isOpening) {
        this.openSubMenuKey = null;
      }
    },
    openSubMenu(subMenus) {
      const key = subMenus.menuValue;
      this.openSubMenuKey = this.openSubMenuKey === key ? null : key;
    },
    isLeafActive(leaf) {
      return this.$route.name === leaf.routeName;
    },
    isDrafted(routeName) {
      return this.draftLinks.includes(routeName);
    },
    toggleDraft(routeName) {
      if (this.isDrafted(routeName)) {
        this.draftLinks = this.draftLinks.filter((name) => name !== routeName);
      } else if (!this.limitReached) {
        this.draftLinks = [...this.draftLinks, routeName];
      }
    },
    openManage() {
      this.draftLinks = [...this.pinnedStore.links];
      this.showManageModal = true;
    },
    closeManage() {
      this.showManageModal = false;
    },
    async savePins() {
      this.savingPins = true;
      const ordered = this.availableLeaves
        .filter((leaf) => this.draftLinks.includes(leaf.routeName))
        .map((leaf) => leaf.routeName);

      try {
        await this.pinnedStore.setLinks(ordered);
        toast(200, "Sidebar links updated");
        this.showManageModal = false;
      } catch (err) {
        showErrors(err);
      } finally {
        this.savingPins = false;
      }
    },
  },
};
</script>

<style scoped>
.sidebar-pinned .submenu-hdr {
  position: relative;
}

.sidebar-pinned__manage {
  color: var(--sidebar-text-color, #6e7c91);
  font-size: 13px;
  line-height: 1;
  opacity: 0.7;
  transition: opacity 0.15s, color 0.15s;
}

.sidebar-pinned__manage:hover {
  opacity: 1;
  color: var(--sidebar-text-hover-color, #333);
}

.sidebar-pinned__empty {
  display: flex;
  align-items: center;
  opacity: 0.7;
}
</style>
