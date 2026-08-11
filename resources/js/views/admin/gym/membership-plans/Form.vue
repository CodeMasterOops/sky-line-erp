<template>
    <VModal
        :show-modal="show"
        modal-class="medium-modal"
        :title="isEdit ? 'Edit Membership Plan' : 'Add Membership Plan'"
        @close-click="close"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="submit">
                <div class="col-md-8">
                    <VInput
                        id="plan_name"
                        v-model="form.name"
                        label="Plan Name"
                        placeholder="Monthly"
                        required
                        :error="errors.name"
                        @validate="validateField('name')"
                    />
                </div>
                <div class="col-md-4">
                    <VInput id="plan_code" v-model="form.code" label="Code" :disabled="isEdit" />
                </div>

                <div class="col-md-6">
                    <label class="form-label">Term</label>
                    <select v-model="form.preset" class="form-select">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="half_yearly">Half-Yearly</option>
                        <option value="yearly">Yearly</option>
                        <option value="custom">Custom…</option>
                    </select>
                </div>

                <template v-if="form.preset === 'custom'">
                    <div class="col-md-3">
                        <VInput
                            id="plan_duration_value"
                            v-model="form.duration_value"
                            input-type="number"
                            label="Length"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit</label>
                        <select v-model="form.duration_unit" class="form-select">
                            <option value="day">Days</option>
                            <option value="week">Weeks</option>
                            <option value="month">Months</option>
                            <option value="year">Years</option>
                        </select>
                    </div>
                </template>

                <div class="col-md-6">
                    <VInput
                        id="plan_price"
                        v-model="form.price"
                        input-type="number"
                        label="Price"
                        required
                        :error="errors.price"
                        @validate="validateField('price')"
                    />
                </div>
                <div class="col-md-6">
                    <VInput id="plan_joining_fee" v-model="form.joining_fee" input-type="number" label="Joining Fee" />
                </div>

                <div class="col-md-4">
                    <VInput
                        id="plan_grace_days"
                        v-model="form.grace_days"
                        input-type="number"
                        label="Grace Days"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="plan_freeze_days"
                        v-model="form.max_freeze_days"
                        input-type="number"
                        label="Max Freeze Days"
                    />
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch pb-2">
                        <input id="plan_is_active" v-model="form.is_active" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="plan_is_active">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <VTextarea id="plan_description" v-model="form.description" label="Description" />
                </div>

                <div class="col-12">
                    <p class="fs-11 text-muted mb-0">
                        Each plan sells through its own service item, so membership invoices post to the
                        ledger exactly like any other sale.
                    </p>
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
import { number, object, string } from 'yup';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { useYup } from '@/helpers/yup';
import VModal from '@/components/base/VModal.vue';
import VInput from '@/components/base/VInput.vue';
import VTextarea from '@/components/base/VTextarea.vue';
import VButton from '@/components/base/VButton.vue';
import { useMembershipPlanStore } from '@/stores/admin/gym/membershipPlan';

const props = defineProps({
    plan: { type: Object, default: null },
});

const show = defineModel('show', { type: Boolean, default: false });
const emit = defineEmits(['saved']);

const planStore = useMembershipPlanStore();

const initialState = {
    name: '',
    code: '',
    description: '',
    preset: 'monthly',
    duration_unit: 'month',
    duration_value: 1,
    price: 0,
    joining_fee: 0,
    grace_days: 0,
    max_freeze_days: 0,
    is_active: true,
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

const isEdit = computed(() => !!props.plan?.id);

const validations = object({
    name: string().required('The plan name is required.'),
    price: number().typeError('A price is required.').min(0, 'The price cannot be negative.'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

watch(
    () => [show.value, props.plan],
    () => {
        if (!show.value) {
            return;
        }

        Object.assign(form, { ...initialState, ...(props.plan ?? {}) });
    },
    { immediate: true },
);

const submit = async () => {
    if (!(await validateForm(validations, form))) {
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = {
            ...form,
            code: form.code || undefined,
            // `custom` is a UI-only marker; the server decides the final preset
            // from the term it is given.
            preset: form.preset === 'custom' ? null : form.preset,
        };

        const res = isEdit.value
            ? await planStore.updatePlan(props.plan.id, payload)
            : await planStore.storePlan(payload);

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
    errors.value = {};
    show.value = false;
};
</script>
