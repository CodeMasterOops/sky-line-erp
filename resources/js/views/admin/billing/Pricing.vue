<template>
  <PageHeader
    title="Plans & pricing"
    subtitle="View your current plan. Contact support to change your subscription."
    hide-action-buttons
  />

  <div v-if="loading" class="text-center py-5">
    <span class="spinner-border text-primary"></span>
  </div>

  <template v-else>
    <div v-if="currentSubscription" class="alert alert-info mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <strong>Current plan:</strong> {{ currentSubscription.plan.name }}
          <span class="badge bg-primary ms-2">{{ currentSubscription.status_label }}</span>
        </div>
        <div class="text-muted">
          {{ currentSubscription.billing_cycle_label }} billing
          <span v-if="currentSubscription.ends_at"> · Renews {{ formatDate(currentSubscription.ends_at) }}</span>
          <span v-else-if="currentSubscription.trial_ends_at"> · Trial ends {{ formatDate(currentSubscription.trial_ends_at) }}</span>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-body pb-1">
            <div class="d-flex justify-content-center align-items-center mb-4">
              <p class="mb-0 me-2">Monthly</p>
              <div class="form-check form-switch">
                <input
                  id="billing-period-switch"
                  v-model="yearly"
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  :aria-label="yearly ? 'Yearly billing' : 'Monthly billing'"
                />
              </div>
              <p class="mb-0">Yearly</p>
            </div>

            <div v-if="!planList.length" class="text-center py-5">
              <p class="text-muted mb-0">No plans are available at the moment. Please contact support.</p>
            </div>

            <div v-else class="row justify-content-center">
              <div
                v-for="plan in planList"
                :key="plan.id"
                class="col-xl-3 col-lg-4 col-md-6 col-sm-12"
              >
                <div
                  :class="[
                    'card mb-3 h-100',
                    isCurrentPlan(plan) ? 'pricing-active position-relative border-primary' : 'bg-light',
                  ]"
                >
                  <span
                    v-if="plan.is_recommended"
                    class="badge bg-pink badge-top"
                  >
                    Recommended
                  </span>
                  <span
                    v-if="isCurrentPlan(plan)"
                    class="badge bg-primary badge-top"
                    style="right: 1rem; left: auto;"
                  >
                    Current Plan
                  </span>
                  <div class="card-body">
                    <p class="mb-0 fw-semibold text-uppercase">{{ plan.name }}</p>
                    <p class="mb-2 text-muted fs-13">{{ plan.description }}</p>
                    <div class="d-flex align-items-center mb-2">
                      <h4 class="mb-0" :class="{ 'fw-bold': yearly }">
                        {{ formatMoney(yearly ? plan.price_yearly : plan.price_monthly) }}
                      </h4>
                      <span class="d-inline-flex ms-1 text-body">
                        {{ yearly ? "/ year" : "/ month" }}
                      </span>
                    </div>
                    <p v-if="yearly" class="text-success fs-12 mb-3">
                      Best value with yearly billing
                    </p>
                    <button
                      type="button"
                      class="btn w-100 mb-3"
                      :class="isCurrentPlan(plan) ? 'btn-primary' : 'btn-secondary'"
                      disabled
                    >
                      {{ isCurrentPlan(plan) ? 'Current Plan' : 'Contact support to change plan' }}
                    </button>
                    <span class="d-block mb-2 fw-medium">Features</span>
                    <ul class="list-unstyled mb-0">
                      <li
                        v-for="(feature, index) in plan.features"
                        :key="index"
                        class="d-flex align-items-start mb-2"
                      >
                        <span class="pricing-check rounded-circle me-2 bg-success flex-shrink-0 mt-1">
                          <i class="ti ti-check fs-10" />
                        </span>
                        <span>{{ feature }}</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup>
import {computed, onMounted, ref} from "vue";
import {storeToRefs} from "pinia";
import {useBillingStore} from "@/stores/admin/billing";

const billingStore = useBillingStore();
const {subscription, plans} = storeToRefs(billingStore);

const yearly = ref(false);
const loading = ref(true);

const currentSubscription = computed(() => subscription.value.data);
const planList = computed(() => plans.value.data ?? []);

onMounted(async () => {
    try {
        await billingStore.loadBillingPage();
        if (currentSubscription.value?.billing_cycle === 'yearly') {
            yearly.value = true;
        }
    } finally {
        loading.value = false;
    }
});

function isCurrentPlan(plan) {
    return currentSubscription.value?.plan?.id === plan.id;
}

function formatMoney(amount) {
    return new Intl.NumberFormat("en-IN", {
        style: "currency",
        currency: "INR",
        maximumFractionDigits: 0,
    }).format(Number(amount || 0));
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString();
}
</script>
