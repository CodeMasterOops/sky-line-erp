<template>
    <VModal
        :show-modal="!!assignModalOpened"
        @close-click="closeAssignModal"
        modal-class="extra-medium-modal"
        title="Assign Subscription">
        <template #modal-body>
            <form @submit.prevent="assignSubscription" class="row g-3">
                <div class="col-md-6">
                    <VMultiselect
                        id="company_id"
                        v-model="form.company_id"
                        label="Company"
                        placeholder="Select company"
                        :options="companies.data"
                        name-prop="company_name"
                        :error="errors.company_id?.[0] ?? errors.company_id"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="plan_id"
                        v-model="form.plan_id"
                        label="Plan"
                        placeholder="Select plan"
                        :options="plans.data"
                        :error="errors.plan_id?.[0] ?? errors.plan_id"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="billing_cycle"
                        v-model="form.billing_cycle"
                        label="Billing Cycle"
                        :options="billingCycleOptions"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="trial_ends_at"
                        v-model="form.trial_ends_at"
                        label="Trial Ends At (optional)"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="ends_at"
                        v-model="form.ends_at"
                        label="Ends At (optional)"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="notes"
                        v-model="form.notes"
                        label="Notes"
                    />
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button @click="closeAssignModal" class="btn btn-cancel" type="button">Cancel</button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {onMounted, reactive, ref} from "vue";
import {storeToRefs} from "pinia";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {useSubscriptionStore} from '@/stores/super-admin/subscription.js';
import {useCompanyStore} from '@/stores/super-admin/company.js';
import {usePlanStore} from '@/stores/super-admin/plan.js';

const subscriptionStore = useSubscriptionStore();
const companyStore = useCompanyStore();
const planStore = usePlanStore();
const assignModalOpened = defineModel('assignModalOpened');

const {companies} = storeToRefs(companyStore);
const {plans} = storeToRefs(planStore);

const billingCycleOptions = [
    { id: 'monthly', name: 'Monthly' },
    { id: 'yearly', name: 'Yearly' },
];

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

onMounted(() => {
    companyStore.getCompanies({filter: {limit: 100}});
    planStore.getPlans({filter: {limit: 100, is_active: true}});
});

const assignSubscription = async () => {
    errors.value = {};
    isSubmitting.value = true;

    try {
        const res = await subscriptionStore.assignSubscription(form);
        toast(res.status, res.data.message);
        closeAssignModal();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
        }
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeAssignModal = () => {
    Object.assign(form, {...initialState});
    errors.value = {};
    assignModalOpened.value = false;
};
</script>
