<template>
  <div class="sidebar-trial-plan-card" aria-label="Subscription">
    <div class="sidebar-trial-plan-card__inner sidebar-trial-plan-card__inner--full">
      <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
        <div class="min-w-0">
          <div class="sidebar-trial-plan-card__label text-uppercase">Current plan</div>
          <div class="sidebar-trial-plan-card__plan fw-semibold text-truncate">Professional</div>
        </div>
        <span class="badge rounded-pill sidebar-trial-plan-card__badge">Active</span>
      </div>
      <div class="sidebar-trial-plan-card__trial mb-2">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="sidebar-trial-plan-card__label">Trial</span>
          <span class="sidebar-trial-plan-card__days">12 days left</span>
        </div>
        <div class="progress sidebar-trial-plan-card__progress" style="height: 4px">
          <div
            class="progress-bar"
            role="progressbar"
            style="width: 40%"
            aria-valuenow="40"
            aria-valuemin="0"
            aria-valuemax="100"
          />
        </div>
      </div>
      <p class="sidebar-trial-plan-card__hint small mb-2 mb-md-3">
        Trial ends <strong>Jan 28, 2026</strong>. Upgrade to keep all features.
      </p>
      <button
        type="button"
        class="btn btn-sm w-100 sidebar-trial-plan-card__btn"
        @click="goToPlans"
      >
        View plans
      </button>
    </div>
    <div
      class="sidebar-trial-plan-card__inner sidebar-trial-plan-card__inner--mini"
      title="Professional · 12 days trial left"
    >
      <i class="ti ti-crown sidebar-trial-plan-card__mini-icon"></i>
      <span class="sidebar-trial-plan-card__mini-text">Pro</span>
    </div>
  </div>
</template>

<script>
export default {
  name: "SidebarTrialPlanCard",
  methods: {
    goToPlans() {
      this.$router.push({ name: "admin.billing-pricing" });
    },
  },
};
</script>

<style scoped>
.sidebar-trial-plan-card {
  pointer-events: auto;
}

.sidebar-trial-plan-card__inner {
  border-radius: 10px;
  background: linear-gradient(
    145deg,
    rgba(19, 88, 241, 0.1) 0%,
    rgba(255, 255, 255, 0.95) 45%,
    rgba(64, 171, 230, 0.06) 100%
  );
  border: 1px solid rgba(19, 88, 241, 0.2);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}

.sidebar-trial-plan-card__inner--full {
  padding: 12px 14px;
}

.sidebar-trial-plan-card__inner--mini {
  display: none;
  padding: 10px 6px;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: center;
}

.sidebar-trial-plan-card__mini-icon {
  font-size: 1.25rem;
  color: #1358f1;
  line-height: 1;
}

.sidebar-trial-plan-card__mini-text {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #001440;
  line-height: 1;
}

.sidebar-trial-plan-card__label {
  font-size: 10px;
  letter-spacing: 0.04em;
  color: #637381;
}

.sidebar-trial-plan-card__plan {
  font-size: 14px;
  color: #001440;
  line-height: 1.3;
}

.sidebar-trial-plan-card__badge {
  font-size: 10px;
  font-weight: 600;
  padding: 0.35em 0.65em;
  background: rgba(19, 88, 241, 0.12);
  color: #0d47c9;
  border: 1px solid rgba(19, 88, 241, 0.28);
}

.sidebar-trial-plan-card__days {
  font-size: 11px;
  font-weight: 600;
  color: #001440;
}

.sidebar-trial-plan-card__hint {
  color: #637381;
  line-height: 1.35;
  margin-bottom: 0;
}

.sidebar-trial-plan-card__progress {
  background: rgba(99, 115, 129, 0.12);
  border-radius: 4px;
}

.sidebar-trial-plan-card__progress .progress-bar {
  background: linear-gradient(90deg, #1358f1, #40abe6);
  border-radius: 4px;
}

.sidebar-trial-plan-card__btn {
  border: 1px solid rgba(19, 88, 241, 0.4);
  color: #1358f1;
  background: rgba(255, 255, 255, 0.85);
  font-weight: 600;
  border-radius: 8px;
}

.sidebar-trial-plan-card__btn:hover {
  background: rgba(19, 88, 241, 0.1);
  color: #0d47c9;
  border-color: #1358f1;
}

:global(body.mini-sidebar) .sidebar-trial-plan-card__inner--full {
  display: none;
}

:global(body.mini-sidebar) .sidebar-trial-plan-card__inner--mini {
  display: flex;
}
</style>
