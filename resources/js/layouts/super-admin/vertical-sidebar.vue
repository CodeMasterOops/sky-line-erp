<template>
  <ul>
    <li class="submenu-open" v-for="item in side_bar_data" :key="item.tittle">
        <h6 class="submenu-hdr">{{ item.tittle }}</h6>
        <ul>
            <template v-for="menu in item.menu" :key="menu.menuValue">
                <li v-if="!menu.hasSubRoute" :class="{ 'active': isMenuActive(menu) }">
                    <router-link v-if="menu.route" :to="menu.route">
                        <i :class="menu.icon" class="fs-16 me-2"></i>
                        <span>{{ menu.menuValue }}</span>
                    </router-link>
                </li>
                <li v-else class="submenu">
                    <a href="javascript:void(0);" @click="expandSubMenus(menu)" 
                    :class="{ subdrop: menu.showSubRoute, active: isActive(menu) }">
                        <i :class="menu.icon" class="fs-16 me-2"></i>
                        <span>{{ menu.menuValue }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul :class="menu.showSubRoute ? 'd-block' : 'd-none'">
                        <li v-for="(subMenu, index) in menu.subMenus" :key="index">
                            <router-link v-if="subMenu.route" :to="subMenu.route">{{ subMenu.menuValue }}</router-link>
                        </li>
                    </ul>
                </li>
                <li v-if="menu.hasSubRouteTwo" class="submenu">
                    <a href="javascript:void(0);" @click="OpenMenu(menu)"
                    :class="{ 
                            subdrop: openMenuItem === menu,
                            active: isActive(menu)
                            }">

                        <i :class="menu.icon" class="fs-16 me-2"></i><span>{{ menu.menuValue }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul :class="{ 'd-block': openMenuItem === menu || isActive(menu), 'd-none': openMenuItem !== menu && !isActive(menu) }">
                        <li v-for="subMenus in menu.subMenus" :key="subMenus.menuValue">
                            <template v-if="!subMenus.customSubmenuTwo">
                                <router-link v-if="subMenus.route" :to="subMenus.route" router-link-active="active">{{ subMenus.menuValue }}</router-link>
                            </template>
                            <template v-else-if="subMenus.customSubmenuTwo">
                                <li class="submenu submenu-two">
                                    <a href="javascript:void(0);" @click="openSubmenuOne(subMenus)"
                                    :class="{ subdrop: openSubmenuOneItem === subMenus, active: isSubActive(subMenus) }">
                                        {{ subMenus.menuValue }}<span class="menu-arrow inside-submenu"></span>
                                    </a>
                                    <ul :class="{ 'd-block': openSubmenuOneItem === subMenus, 'd-none': openSubmenuOneItem !== subMenus }">
                                        <li v-for="subMenuTwo in subMenus.subMenusTwo" :key="subMenuTwo.menuValue">
                                            <router-link v-if="subMenuTwo.route" :to="subMenuTwo.route">{{ subMenuTwo.menuValue }}</router-link>
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
import side_bar_data from "@/assets/json/super-admin-sidebar.json";

export default {
  data() {
      return {
          side_bar_data: side_bar_data,
          openMenuItem: null,
          openSubmenuOneItem: null,
          multiLevelOpen: false,
      }
  },
  computed: {
      isMenuActive() {
          return (menu) => {
              return this.$route.path === menu.active_link;                
          };
      },
      isActive(){
          return (menu) => {
              let result = this.$route.path.split('/').filter(part => part);
              // Assuming super-admin routes are /super-admin/...
              // We might need to adjust logic depending on how deep the routes go or just rely on active_link
               // Simple check if current path includes the active link base
               if(menu.active_link) {
                   return this.$route.path.startsWith(menu.active_link);
               }
               return false;
          }
      },
      isSubActive(){
          return (menu) => {
              // similar logic if needed
              return false;
          }
      }
  },
  methods: {
      expandSubMenus(menu) {
          const isMiniSidebar = document.body.classList.contains('mini-sidebar');
          
          this.side_bar_data.forEach((item) => {
              if(item.menu) {
                  item.menu.forEach((subMenu) => {
                      if (subMenu !== menu) {
                          subMenu.showSubRoute = false;
                      }
                  });
              }
          });
          
          if (isMiniSidebar) {
              menu.showSubRoute = true;
          } else {
              menu.showSubRoute = !menu.showSubRoute;
          }
      },
      OpenMenu(menu) {
          const isMiniSidebar = document.body.classList.contains('mini-sidebar');
          
          this.side_bar_data.forEach((item) => {
               if(item.menu) {
                  item.menu.forEach((subMenu) => {
                      subMenu.showSubRoute = false; 
                  });
               }
          });
          
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
          const isMiniSidebar = document.body.classList.contains('mini-sidebar');
          
          if (isMiniSidebar) {
              this.openSubmenuOneItem = subMenus;
          } else {
              this.openSubmenuOneItem = this.openSubmenuOneItem === subMenus ? null : subMenus;
          }
      },
  }
}
</script>
