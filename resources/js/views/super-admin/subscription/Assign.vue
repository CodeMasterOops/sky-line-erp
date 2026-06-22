<template>
    <VModal
        :show-modal="!!assignModalOpened"
        @close-click="closeAssignModal"
        modal-class="extra-medium-modal"
        title="Assign Subscription">
        <template #modal-body>
            <form @submit.prevent="assignSubscription" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Company</label>
                    <select v-model="form.company_id" class="form-select">
                        <option value="">Select company</option>
                        <option v-for="company in companies.data" :key="company.id" :value="company.id">
                            {{ company.company_name }}
                        </option>
                    </select>
                    <small v-if="errors.company_id" class="text-danger">{{ errors.company_id }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Plan</label>
                    <select v-model="form.plan_id" class="form-select">
                        <option value="">Select plan</option>
                        <option v-for="plan in plans.data" :key="plan.id" :value="plan.id">
                            {{ plan.name }}
                        </option>
                    </select>
                    <small v-if="errors.plan_id" class="text-danger">{{ errors.plan_id }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Billing Cycle</label>
                    <select v-model="form.billing_cycle" class="form-select">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
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
