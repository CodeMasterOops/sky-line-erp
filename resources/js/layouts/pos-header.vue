<template>
  <!-- Header -->
  <div class="header pos-header">
    <!-- Logo -->
    <div class="header-left active">
      <router-link :to="{ name: 'admin.dashboard' }" class="logo logo-normal">
        <img src="@/assets/images/logo.svg" alt="Logo" />
      </router-link>
      <router-link :to="{ name: 'admin.dashboard' }" class="logo logo-white">
        <img src="@/assets/images/logo-white.svg" alt="Logo" />
      </router-link>
      <router-link :to="{ name: 'admin.dashboard' }" class="logo-small">
        <img src="@/assets/images/logo.svg" alt="Logo" />
      </router-link>
    </div>

    <a id="mobile_btn" class="mobile_btn d-none" href="#sidebar">
      <span class="bar-icon">
        <span></span><span></span><span></span>
      </span>
    </a>

    <!-- Header Menu -->
    <ul class="nav user-menu">
      <!-- Live Clock -->
      <li class="nav-item time-nav">
        <span class="bg-teal text-white d-inline-flex align-items-center">
          <i class="ti ti-clock me-2"></i>{{ currentTime }}
        </span>
      </li>

      <li class="nav-item pos-nav">
        <router-link
          :to="{ name: 'admin.dashboard' }"
          class="btn btn-purple btn-md d-inline-flex align-items-center"
        >
          <i class="ti ti-world me-1"></i>Dashboard
        </router-link>
      </li>

      <!-- Warehouse / Store Selector -->
      <li class="nav-item dropdown has-arrow main-drop select-store-dropdown">
        <a
          href="javascript:void(0);"
          class="dropdown-toggle nav-link select-store"
          data-bs-toggle="dropdown"
        >
          <span class="user-info">
            <span class="user-letter">
              <img :src="'/img/store/store-01.png'" alt="Store Logo" class="img-fluid" />
            </span>
            <span class="user-detail">
              <span class="user-name">{{ selectedWarehouseName }}</span>
            </span>
          </span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <a
            href="javascript:void(0);"
            class="dropdown-item"
            :class="{ active: selectedWarehouseId === null }"
            @click="selectWarehouse({ id: null, name: 'All Warehouses' })"
          >
            <img :src="'/img/store/store-01.png'" alt="Store Logo" class="img-fluid" />
            All Warehouses
          </a>
          <a
            v-for="wh in warehouses"
            :key="wh.id"
            href="javascript:void(0);"
            class="dropdown-item"
            :class="{ active: wh.id === selectedWarehouseId }"
            @click="selectWarehouse(wh)"
          >
            <img :src="'/img/store/store-01.png'" alt="Store Logo" class="img-fluid" />
            {{ wh.name }}
          </a>
        </div>
      </li>

      <!-- Calculator -->
      <li class="nav-item nav-item-box">
        <a
          href="javascript:void(0);"
          data-bs-toggle="modal"
          data-bs-target="#calculator"
          class="bg-orange border-orange text-white"
        ><i class="ti ti-calculator"></i></a>
      </li>

      <!-- Fullscreen -->
      <li class="nav-item nav-item-box">
        <a
          href="javascript:void(0);"
          id="btnFullscreen"
          data-bs-toggle="tooltip"
          data-bs-placement="top"
          data-bs-title="Maximize"
          @click="initFullScreen"
        ><i class="ti ti-maximize"></i></a>
      </li>

      <!-- Cash Register -->
      <li
        class="nav-item nav-item-box"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        data-bs-title="Cash Register"
      >
        <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#cash-register">
          <i class="ti ti-cash"></i>
        </a>
      </li>

      <!-- Print Last Receipt -->
      <li
        class="nav-item nav-item-box"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        data-bs-title="Print Last Receipt"
      >
        <a
          href="javascript:void(0);"
          @click="printLastReceipt"
          data-bs-toggle="modal"
          data-bs-target="#print-receipt"
        ><i class="ti ti-printer"></i></a>
      </li>

      <!-- Today's Sale -->
      <li
        class="nav-item nav-item-box"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        data-bs-title="Today's Sale"
      >
        <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#today-sale">
          <i class="ti ti-progress"></i>
        </a>
      </li>

      <!-- Today's Profit -->
      <li
        class="nav-item nav-item-box"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        data-bs-title="Today's Profit"
      >
        <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#today-profit">
          <i class="ti ti-chart-infographic"></i>
        </a>
      </li>

      <!-- POS Settings -->
      <li
        class="nav-item nav-item-box"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        data-bs-title="Settings"
      >
        <router-link :to="{ name: 'admin.setting' }">
          <i class="ti ti-settings"></i>
        </router-link>
      </li>

      <!-- User dropdown -->
      <li class="nav-item dropdown has-arrow main-drop profile-nav">
        <a href="javascript:void(0);" class="nav-link userset" data-bs-toggle="dropdown">
          <span class="user-info p-0">
            <span class="user-letter">
              <img :src="userIcon" alt="Profile" class="img-fluid" />
            </span>
          </span>
        </a>
        <div class="dropdown-menu menu-drop-user">
          <div class="profilename">
            <div class="profileset">
              <span class="user-img">
                <img :src="userIcon" alt="Profile" />
                <span class="status online"></span>
              </span>
              <div class="profilesets">
                <h6>{{ userName }}</h6>
                <h5>{{ userRole }}</h5>
              </div>
            </div>
            <hr class="m-0" />
            <router-link class="dropdown-item" :to="{ name: 'admin.profile' }">
              <i class="ti ti-user me-2"></i>My Profile
            </router-link>
            <router-link class="dropdown-item" :to="{ name: 'admin.setting' }">
              <i class="ti ti-settings me-2"></i>Settings
            </router-link>
            <hr class="m-0" />
            <button class="dropdown-item logout pb-0" type="button" @click.prevent="logout">
              <i class="ti ti-logout me-2"></i>Logout
            </button>
          </div>
        </div>
      </li>
    </ul>

    <!-- Mobile Menu -->
    <div class="dropdown mobile-user-menu">
      <a
        href="javascript:void(0);"
        class="nav-link dropdown-toggle"
        data-bs-toggle="dropdown"
        aria-expanded="false"
      ><i class="fa fa-ellipsis-v"></i></a>
      <div class="dropdown-menu dropdown-menu-right">
        <router-link class="dropdown-item" :to="{ name: 'admin.profile' }">My Profile</router-link>
        <router-link class="dropdown-item" :to="{ name: 'admin.setting' }">Settings</router-link>
        <button class="dropdown-item" type="button" @click.prevent="logout">Logout</button>
      </div>
    </div>
  </div>
  <!-- /Header -->

  <pos-loader></pos-loader>
</template>

<script>
import userIconImg from '@/assets/images/user-icon.png';
import { useAdminAuthStore } from '@/stores/admin/auth';
import { usePosStore } from '@/stores/admin/pos/pos.js';
import showErrors from '@/helpers/showErrors';
import { useToast } from 'vue-toastification';

export default {
  emits: ['warehouse-changed'],

  setup() {
    const posStore = usePosStore();
    return { posStore };
  },

  data() {
    return {
      userIcon: userIconImg,
      currentTime: '',
      clockInterval: null,
    };
  },

  mounted() {
    this.updateClock();
    this.clockInterval = setInterval(this.updateClock, 1000);
  },

  beforeUnmount() {
    clearInterval(this.clockInterval);
  },

  computed: {
    warehouses() {
      return this.posStore.warehouses;
    },
    selectedWarehouseId() {
      return this.posStore.selectedWarehouseId;
    },
    selectedWarehouseName() {
      if (this.posStore.selectedWarehouseId === null) return 'All Warehouses';
      const wh = this.posStore.warehouses.find(w => w.id === this.posStore.selectedWarehouseId);
      return wh?.name ?? 'All Warehouses';
    },
    userName() {
      const user = useAdminAuthStore().user;
      return user?.name ?? 'Admin';
    },
    userRole() {
      const user = useAdminAuthStore().user;
      return user?.role?.name ?? 'Admin';
    },
  },

  methods: {
    updateClock() {
      const now = new Date();
      this.currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    },

    selectWarehouse(wh) {
      this.posStore.setWarehouse(wh.id);
      this.$emit('warehouse-changed', wh.id);
    },

    printLastReceipt() {
      if (!this.posStore.lastSale) {
        useToast().warning('No recent sale to print');
      }
    },

    initFullScreen() {
      document.body.classList.toggle('fullscreen-enable');
      if (
        !document.fullscreenElement &&
        !document.mozFullScreenElement &&
        !document.webkitFullscreenElement
      ) {
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
          document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
          document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
      } else {
        if (document.cancelFullScreen) {
          document.cancelFullScreen();
        } else if (document.mozCancelFullScreen) {
          document.mozCancelFullScreen();
        } else if (document.webkitCancelFullScreen) {
          document.webkitCancelFullScreen();
        }
      }
    },

    async logout() {
      try {
        const authStore = useAdminAuthStore();
        const res = await authStore.logout();
        useToast().success(res.data.message);
        await this.$router.push({ name: 'admin.login' });
      } catch (e) {
        showErrors(e);
      }
    },
  },
};
</script>
