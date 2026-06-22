<template>
    <VModal
        :show-modal="!!planId"
        @close-click="closeEditModal"
        modal-class="extra-medium-modal"
        title="Edit Plan">
        <template #modal-body>
            <form v-if="!plan.loading" @submit.prevent="updatePlan" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="edit_name"
                        v-model="form.name"
                        label="Plan Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="edit_sort_order"
                        v-model="form.sort_order"
                        type="number"
                        label="Sort Order"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="edit_description"
                        v-model="form.description"
                        label="Description"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="edit_price_monthly"
                        v-model="form.price_monthly"
                        type="number"
                        label="Monthly Price"
                        @validate="validateField('price_monthly')"
                        :error="errors.price_monthly"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="edit_price_yearly"
                        v-model="form.price_yearly"
                        type="number"
                        label="Yearly Price"
                        @validate="validateField('price_yearly')"
                        :error="errors.price_yearly"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="edit_branch_limit"
                        v-model="form.branch_limit"
                        type="number"
                        label="Branch Limit"
                        placeholder="Leave empty for unlimited"
                    />
                </div>
                <div class="col-12">
                    <label class="form-label">Features (one per line)</label>
                    <textarea v-model="featuresText" class="form-control" rows="4"></textarea>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input id="edit_is_active" v-model="form.is_active" class="form-check-input" type="checkbox" :disabled="form.is_default">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input id="edit_is_recommended" v-model="form.is_recommended" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="edit_is_recommended">Recommended</label>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button @click="closeEditModal" class="btn btn-cancel" type="button">Cancel</button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
            <div v-else class="text-center py-4">
                <span class="spinner-border text-primary"></span>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, watch} from "vue";
import {storeToRefs} from "pinia";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {number, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {usePlanStore} from '@/stores/super-admin/plan.js';

const planStore = usePlanStore();
const planId = defineModel('planId');
const {plan} = storeToRefs(planStore);

const form = reactive({
    name: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    branch_limit: null,
    is_active: true,
    is_default: false,
    is_recommended: false,
    sort_order: 0,
});

const featuresText = ref('');
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Plan name is required.'),
    price_monthly: number().typeError('Monthly price is required.').min(0),
    price_yearly: number().typeError('Yearly price is required.').min(0),
});

const {errors, validateField, validateForm} = useYup(form, validations);

watch(planId, async (id) => {
    if (!id) {
        return;
    }

    await planStore.getPlan(id);
    const data = plan.value.data;
    Object.assign(form, {
        name: data.name,
        description: data.description,
        price_monthly: data.price_monthly,
        price_yearly: data.price_yearly,
        branch_limit: data.branch_limit ?? null,
        is_active: data.is_active,
        is_default: data.is_default,
        is_recommended: data.is_recommended,
        sort_order: data.sort_order,
    });
    featuresText.value = (data.features || []).join('\n');
});

const payload = computed(() => ({
    ...form,
    features: featuresText.value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean),
}));

const updatePlan = async () => {
    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await planStore.updatePlan(planId.value, payload.value);
        toast(res.status, res.data.message);
        closeEditModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeEditModal = () => {
    planId.value = '';
    errors.value = {};
};
</script>
