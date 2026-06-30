<template>
    <VModal
        :show-modal="!!company"
        @close-click="closeModal"
        modal-class="extra-medium-modal"
        title="Upgrade Plan"
    >
        <template #modal-body>
            <form @submit.prevent="upgradePlan" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Company</label>
                    <input
                        type="text"
                        class="form-control"
                        :value="company?.company_name"
                        readonly
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Current plan</label>
                    <input
                        type="text"
                        class="form-control"
                        :value="currentPlanLabel"
                        readonly
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="plan_id"
                        v-model="form.plan_id"
                        label="New plan"
                        placeholder="Select plan"
                        :options="planOptions"
                        required
                        :error="errors.plan_id?.[0] ?? errors.plan_id"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="billing_cycle"
                        v-model="form.billing_cycle"
                        label="Billing cycle"
                        :options="billingCycleOptions"
                        required
                        :error="errors.billing_cycle?.[0] ?? errors.billing_cycle"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="upgrade_trial_ends_at"
                        v-model="form.trial_ends_at"
                        label="Trial ends at (optional)"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="upgrade_ends_at"
                        v-model="form.ends_at"
                        label="Subscription expires at"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="upgrade_notes"
                        v-model="form.notes"
                        label="Notes"
                        placeholder="Optional note for this plan change"
                    />
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">
                        The current active subscription will be cancelled and replaced with the selected plan.
                    </p>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-cancel" type="button" @click="closeModal">
                        Cancel
                    </button>
                    <VButton :loading="isSubmitting" btn-label="Upgrade Plan" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useSubscriptionStore} from '@/stores/super-admin/subscription.js';
import {usePlanStore} from '@/stores/super-admin/plan.js';
import {formatSuperAdminMoney} from '@/helpers/formatSuperAdminMoney.js';

const subscriptionStore = useSubscriptionStore();
const planStore = usePlanStore();
const {plans} = storeToRefs(planStore);

const billingCycleOptions = [
    { id: 'monthly', name: 'Monthly' },
    { id: 'yearly', name: 'Yearly' },
];

const company = defineModel('company');
const emit = defineEmits(['upgraded']);

const defaultEndsAt = (cycle) => {
    const d = new Date();
    if (cycle === 'yearly') {
        d.setFullYear(d.getFullYear() + 1);
    } else {
        d.setMonth(d.getMonth() + 1);
    }
    return d.toISOString().split('T')[0];
};

const initialState = {
    company_id: '',
    plan_id: '',
    billing_cycle: 'monthly',
    trial_ends_at: '',
    ends_at: defaultEndsAt('monthly'),
    notes: '',
};

const form = reactive({...initialState});
const errors = ref({});
const isSubmitting = ref(false);

const currentPlanLabel = computed(() => {
    const subscription = company.value?.current_subscription;
    if (!subscription?.plan?.name) {
        return 'No active plan';
    }

    const cycle = subscription.billing_cycle_label || subscription.billing_cycle || '';
    return cycle ? `${subscription.plan.name} (${cycle})` : subscription.plan.name;
});

const planLabel = (plan) => {
    const monthly = formatSuperAdminMoney(plan.price_monthly);
    const yearly = formatSuperAdminMoney(plan.price_yearly);

    return `${plan.name} — ${monthly} / ${yearly}`;
};

const planOptions = computed(() =>
    plans.value.data.map(plan => ({
        id: plan.id,
        name: planLabel(plan),
    }))
);

watch(company, (selected) => {
    if (!selected) {
        return;
    }

    planStore.getPlans({filter: {limit: 100, is_active: true}});

    const cycle = selected.current_subscription?.billing_cycle || 'monthly';
    form.company_id = selected.id;
    form.plan_id = '';
    form.billing_cycle = cycle;
    form.trial_ends_at = '';
    form.ends_at = defaultEndsAt(cycle);
    form.notes = '';
    errors.value = {};
});

watch(() => form.billing_cycle, (cycle) => {
    form.ends_at = defaultEndsAt(cycle);
});

const upgradePlan = async () => {
    errors.value = {};
    isSubmitting.value = true;

    try {
        const res = await subscriptionStore.assignSubscription({...form});
        toast(res.status, res.data.message);
        emit('upgraded');
        closeModal();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
        }
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    Object.assign(form, {...initialState});
    errors.value = {};
    company.value = null;
};
</script>
