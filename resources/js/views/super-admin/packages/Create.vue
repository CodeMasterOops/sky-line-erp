<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeCreateModal"
        modal-class="extra-medium-modal"
        title="Add Plan">
        <template #modal-body>
            <form @submit.prevent="storePlan" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Plan Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="sort_order"
                        v-model="form.sort_order"
                        type="number"
                        label="Sort Order"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="description"
                        v-model="form.description"
                        label="Description"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="price_monthly"
                        v-model="form.price_monthly"
                        type="number"
                        label="Monthly Price"
                        @validate="validateField('price_monthly')"
                        :error="errors.price_monthly"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="price_yearly"
                        v-model="form.price_yearly"
                        type="number"
                        label="Yearly Price"
                        @validate="validateField('price_yearly')"
                        :error="errors.price_yearly"
                    />
                </div>
                <div class="col-12">
                    <label class="form-label">Features (one per line)</label>
                    <textarea
                        v-model="featuresText"
                        class="form-control"
                        rows="4"
                        placeholder="Basic accounting&#10;Up to 5 users"
                    ></textarea>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input id="is_active" v-model="form.is_active" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input id="is_recommended" v-model="form.is_recommended" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="is_recommended">Recommended</label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">Close</button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {number, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {usePlanStore} from '@/stores/super-admin/plan.js';

const planStore = usePlanStore();
const createModalOpened = defineModel('createModalOpened');

const initialState = {
    name: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    features: [],
    is_active: true,
    is_default: false,
    is_recommended: false,
    sort_order: 0,
};

const form = reactive({...initialState});
const featuresText = ref('');
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Plan name is required.'),
    price_monthly: number().typeError('Monthly price is required.').min(0),
    price_yearly: number().typeError('Yearly price is required.').min(0),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const payload = computed(() => ({
    ...form,
    features: featuresText.value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean),
}));

const storePlan = async () => {
    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await planStore.storePlan(payload.value);
        toast(res.status, res.data.message);
        closeCreateModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeCreateModal = () => {
    Object.assign(form, {...initialState});
    featuresText.value = '';
    errors.value = {};
    createModalOpened.value = false;
};
</script>
