<template>
    <VModal
        :show-modal="show"
        modal-class="medium-modal"
        :title="isRenewal ? 'Renew Membership' : 'Assign Membership'"
        @close-click="close"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="submit">
                <div v-if="memberLabel" class="col-12">
                    <div class="alert alert-light border mb-0 py-2">
                        <span class="fw-medium">{{ memberLabel }}</span>
                        <span v-if="isRenewal && currentEndDate" class="text-muted fs-12">
                            · current term ends {{ currentEndDate }}
                        </span>
                    </div>
                </div>

                <div class="col-md-7">
                    <label class="form-label">Membership Plan <span class="text-danger">*</span></label>
                    <select v-model="form.membership_plan_id" class="form-select" @change="applyPlanPrice">
                        <option :value="null" disabled>Choose a plan</option>
                        <option v-for="plan in activePlans" :key="plan.id" :value="plan.id">
                            {{ plan.name }} — {{ plan.duration_label }} ({{ plan.price }})
                        </option>
                    </select>
                    <div v-if="errors.membership_plan_id" class="text-danger fs-11 mt-1">
                        {{ errors.membership_plan_id }}
                    </div>
                </div>

                <div class="col-md-5">
                    <VDatepicker
                        id="membership_start_date"
                        v-model="form.start_date"
                        label="Start Date"
                    />
                    <div class="fs-11 text-muted mt-1">
                        Leave as suggested to keep the terms continuous.
                    </div>
                </div>

                <div class="col-md-4">
                    <VInput id="membership_price" v-model="form.price" input-type="number" label="Price" />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="membership_discount"
                        v-model="form.discount_amount"
                        input-type="number"
                        label="Discount"
                    />
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch pb-2">
                        <input
                            id="membership_create_invoice"
                            v-model="form.create_invoice"
                            class="form-check-input"
                            type="checkbox"
                        >
                        <label class="form-check-label" for="membership_create_invoice">Raise invoice</label>
                    </div>
                </div>

                <div class="col-12">
                    <VTextarea id="membership_notes" v-model="form.notes" label="Notes" />
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between border-top pt-2">
                        <span class="text-muted fs-12">Payable</span>
                        <span class="fw-medium">{{ payable }}</span>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-cancel" type="button" @click="close">Cancel</button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import VModal from '@/components/base/VModal.vue';
import VInput from '@/components/base/VInput.vue';
import VTextarea from '@/components/base/VTextarea.vue';
import VButton from '@/components/base/VButton.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import { useMembershipStore } from '@/stores/admin/gym/membership';
import { useMembershipPlanStore } from '@/stores/admin/gym/membershipPlan';

const props = defineProps({
    /** The member being sold a term (assignment). */
    member: { type: Object, default: null },
    /** The term being renewed, when this is a renewal. */
    membership: { type: Object, default: null },
});

const show = defineModel('show', { type: Boolean, default: false });
const emit = defineEmits(['saved']);

const membershipStore = useMembershipStore();
const planStore = useMembershipPlanStore();
const { plans } = storeToRefs(planStore);

const activePlans = computed(() => plans.value.data.filter((plan) => plan.is_active));

const isRenewal = computed(() => !!props.membership?.id);

const memberLabel = computed(() =>
    props.membership?.member_name ?? props.member?.name ?? '',
);

const currentEndDate = computed(() => props.membership?.end_date ?? null);

const initialState = {
    membership_plan_id: null,
    start_date: '',
    price: 0,
    discount_amount: 0,
    create_invoice: true,
    notes: '',
};

const form = reactive({ ...initialState });
const errors = reactive({});
const isSubmitting = ref(false);

const payable = computed(() =>
    Math.max(0, (Number(form.price) || 0) - (Number(form.discount_amount) || 0)),
);

watch(
    () => [show.value, props.member, props.membership],
    () => {
        if (!show.value) {
            return;
        }

        planStore.getPlans({ filter: { limit: 100 } });

        Object.assign(form, { ...initialState });
        errors.membership_plan_id = '';

        if (props.membership) {
            form.membership_plan_id = props.membership.membership_plan_id;
            form.price = props.membership.price;
        }
    },
    { immediate: true },
);

const applyPlanPrice = () => {
    const plan = activePlans.value.find((p) => p.id === form.membership_plan_id);

    if (plan) {
        form.price = plan.price;
    }
};

const submit = async () => {
    if (!form.membership_plan_id) {
        errors.membership_plan_id = 'Choose a membership plan.';
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = {
            membership_plan_id: form.membership_plan_id,
            start_date: form.start_date || undefined,
            price: form.price,
            discount_amount: form.discount_amount,
            create_invoice: form.create_invoice,
            notes: form.notes || undefined,
        };

        const res = isRenewal.value
            ? await membershipStore.renew(props.membership.id, payload)
            : await membershipStore.assign({ ...payload, member_id: props.member.id });

        toast(res.status, res.data.message);
        emit('saved');
        close();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    Object.assign(form, { ...initialState });
    show.value = false;
};
</script>
