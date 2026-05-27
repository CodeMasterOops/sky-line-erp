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
                    <label class="form-label">New plan <span class="text-danger">*</span></label>
                    <select
                        v-model="form.plan_id"
                        class="form-select"
                        :class="{ 'is-invalid': errors.plan_id }"
                    >
                        <option value="">Select plan</option>
                        <option
                            v-for="plan in plans.data"
                            :key="plan.id"
                            :value="plan.id"
                        >
                            {{ planLabel(plan) }}
                        </option>
                    </select>
                    <small v-if="errors.plan_id" class="text-danger">{{ errors.plan_id[0] || errors.plan_id }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Billing cycle <span class="text-danger">*</span></label>
                    <select v-model="form.billing_cycle" class="form-select">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    <small v-if="errors.billing_cycle" class="text-danger">{{ errors.billing_cycle[0] || errors.billing_cycle }}</small>
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
                        label="Ends at (optional)"
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
                <div class="col-12 text-end">
                    <button class="btn btn-secondary me-1" type="button" @click="closeModal">
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

const company = defineModel('company');
const emit = defineEmits(['upgraded']);

const initialState = {
    company_id: '',
    plan_id: '',
    billing_cycle: 'monthly',
    trial_ends_at: '',
    ends_at: '',
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

watch(company, (selected) => {
    if (!selected) {
        return;
    }

    planStore.getPlans({filter: {limit: 100, is_active: true}});

    form.company_id = selected.id;
    form.plan_id = '';
    form.billing_cycle = selected.current_subscription?.billing_cycle || 'monthly';
    form.trial_ends_at = '';
    form.ends_at = '';
    form.notes = '';
    errors.value = {};
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
