<template>
  <PageHeader
    title="Plans & pricing"
    subtitle="Choose the plan that fits your team. Switch between monthly and yearly billing anytime."
    hide-action-buttons
  />
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
          <div class="row justify-content-center">
            <div
              v-for="plan in plans"
              :key="plan.name"
              class="col-xl-3 col-lg-4 col-md-6 col-sm-12"
            >
              <div
                :class="[
                  'card mb-3',
                  plan.recommended
                    ? 'pricing-active position-relative'
                    : 'bg-light',
                ]"
              >
                <span
                  v-if="plan.recommended"
                  class="badge bg-pink badge-top"
                >
                  Recommended
                </span>
                <div class="card-body">
                  <p class="mb-1">{{ plan.name }}</p>
                  <div class="d-flex align-items-center mb-2">
                    <h4 class="mb-0">
                      {{ formatMoney(yearly ? plan.yearly : plan.monthly) }}
                    </h4>
                    <span class="d-inline-flex ms-1 text-body">
                      {{ yearly ? "/ Per Year" : "/ Per Month" }}
                    </span>
                  </div>
                  <p class="mb-3">Feature for {{ plan.userLimit }}</p>
                  <button
                    type="button"
                    class="btn btn-secondary w-100 mb-3"
                    @click="subscribe(plan)"
                  >
                    Subscribe Now
                  </button>
                  <span class="d-block mb-1">Features</span>
                  <p class="mb-2">{{ plan.featuresHeading }}</p>
                  <ul class="list-unstyled mb-0">
                    <li
                      v-for="(f, i) in plan.features"
                      :key="i"
                      class="d-flex align-items-center"
                      :class="i < plan.features.length - 1 ? 'mb-2' : ''"
                    >
                      <span
                        :class="[
                          'pricing-check rounded-circle me-2',
                          f.included
                            ? 'bg-success'
                            : 'bg-secondary-transparent',
                        ]"
                      >
                        <i class="ti ti-check fs-10" />
                      </span>
                      {{ f.label }}
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

<script setup>
import { ref } from "vue";
import { useToast } from "vue-toastification";

const toast = useToast();

const yearly = ref(false);

const featureSets = {
  basic: [
    { label: "Customer Profiles", included: true },
    { label: "Inventory Management", included: true },
    { label: "Discounts & Promotions", included: true },
    { label: "24/7 Email & Chat Support", included: false },
    { label: "API Access & Integrations", included: false },
    { label: "Reports & Analytics", included: false },
  ],
  business: [
    { label: "Customer Profiles", included: true },
    { label: "Inventory Management", included: true },
    { label: "Discounts & Promotions", included: true },
    { label: "24/7 Email & Chat Support", included: true },
    { label: "API Access & Integrations", included: false },
    { label: "Reports & Analytics", included: false },
  ],
  premium: [
    { label: "Customer Profiles", included: true },
    { label: "Inventory Management", included: true },
    { label: "Discounts & Promotions", included: true },
    { label: "24/7 Email & Chat Support", included: true },
    { label: "API Access & Integrations", included: true },
    { label: "Reports & Analytics", included: false },
  ],
  enterprise: [
    { label: "Customer Profiles", included: true },
    { label: "Inventory Management", included: true },
    { label: "Discounts & Promotions", included: true },
    { label: "24/7 Email & Chat Support", included: true },
    { label: "API Access & Integrations", included: true },
    { label: "Reports & Analytics", included: true },
  ],
};

const plans = ref([
  {
    name: "Basic Plan",
    userLimit: "up to 10 users",
    monthly: 29,
    yearly: 290,
    recommended: false,
    featuresHeading: "Includes in this basic plan",
    features: featureSets.basic,
  },
  {
    name: "Business Plan",
    userLimit: "up to 22 users",
    monthly: 69,
    yearly: 690,
    recommended: true,
    featuresHeading: "Includes in this business plan",
    features: featureSets.business,
  },
  {
    name: "Premium Plan",
    userLimit: "up to 33 users",
    monthly: 99,
    yearly: 990,
    recommended: false,
    featuresHeading: "Includes in this premium plan",
    features: featureSets.premium,
  },
  {
    name: "Enterprise Plan",
    userLimit: "up to Unlimited users",
    monthly: 199,
    yearly: 1990,
    recommended: false,
    featuresHeading: "Includes in this enterprise plan",
    features: featureSets.enterprise,
  },
]);

function formatMoney(amount) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
  }).format(amount);
}

function subscribe(plan) {
  toast.success(
    `Subscribe: ${plan.name} (${yearly.value ? "yearly" : "monthly"}) — connect billing when ready.`
  );
}
</script>
