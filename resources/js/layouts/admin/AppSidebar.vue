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
        </div>

        <div class="admin-sidebar-shell__footer">
            <div class="sidebar-plan-card">
                <div class="sidebar-plan-card__body">
                    <div class="sidebar-plan-card__icon">
                        <i class="ti ti-crown"></i>
                    </div>
                    <div class="sidebar-plan-card__info">
                        <p class="sidebar-plan-card__label">Current Plan</p>
                        <p class="sidebar-plan-card__name">{{ planName }}</p>
                        <p v-if="planExpiry" class="sidebar-plan-card__expiry">{{ planExpiry }}</p>
                    </div>
                    <router-link
                        :to="{ name: 'admin.billing-pricing' }"
                        class="sidebar-plan-card__action"
                        title="Manage plan"
                    >
                        <i class="ti ti-arrow-right"></i>
                    </router-link>
                </div>
            </div>

            <router-link :to="{ name: 'admin.support' }" class="sidebar-support-link">
                <i class="ti ti-headset fs-16 me-2"></i>
                <span>Support</span>
            </router-link>
        </div>
    </div>
</template>
<script>
import simplebar from "simplebar-vue";
import "simplebar-vue/dist/simplebar.min.css";
import { useBillingStore } from "@/stores/admin/billing";

export default {
  components: {
    simplebar,
  },
  data() {
    return {
      sidebarClass: "",
    };
  },
  computed: {
    planName() {
      const billing = useBillingStore();
      return billing.subscription.data?.plan?.name ?? "Free";
    },
    planExpiry() {
      const sub = useBillingStore().subscription.data;
      if (!sub) { return null; }

      const dateStr = sub.status === 'trialing' ? sub.trial_ends_at : sub.ends_at;
      if (!dateStr) { return null; }

      const expiry = new Date(dateStr);
      const now = new Date();
      const diffDays = Math.ceil((expiry - now) / (1000 * 60 * 60 * 24));

      if (diffDays < 0) { return 'Plan expired'; }
      if (diffDays === 0) { return 'Expires today'; }
      if (diffDays <= 7) { return `Expires in ${diffDays}d`; }

      return `Expires ${expiry.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
    },
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
    if (
      this.$route.path.startsWith("/pos")
    ) {
      this.sidebarClass = "d-none";
    }
    useBillingStore().getSubscription();
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
  overflow: hidden;
}

.admin-sidebar-shell__scroll {
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
}

.admin-sidebar-shell__footer {
  flex: 0 0 auto;
  padding: 8px;
  border-top: 1px solid rgba(0, 0, 0, 0.08);
}

/* Plan card */
.sidebar-plan-card {
  border-radius: 10px;
  background: #0339a7;
  margin-bottom: 6px;
  overflow: hidden;
}

.sidebar-plan-card__body {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
}

.sidebar-plan-card__icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 16px;
  color: #fff;
}

.sidebar-plan-card__info {
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.sidebar-plan-card__label {
  margin: 0;
  font-size: 10px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.75);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  line-height: 1.2;
}

.sidebar-plan-card__name {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}

.sidebar-plan-card__expiry {
  margin: 2px 0 0;
  font-size: 10px;
  font-weight: 400;
  color: rgba(255, 255, 255, 0.7);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}

.sidebar-plan-card__action {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #fff;
  font-size: 14px;
  transition: background 0.2s;
  text-decoration: none;
}

.sidebar-plan-card__action:hover {
  background: rgba(255, 255, 255, 0.35);
  color: #fff;
}

/* Support link */
.sidebar-support-link {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  color: var(--sidebar-text-color, #6e7c91);
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}

.sidebar-support-link:hover,
.sidebar-support-link.router-link-active {
  background: rgba(0, 0, 0, 0.06);
  color: var(--sidebar-text-hover-color, #333);
}

</style>
