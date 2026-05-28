<template>
    <VModal
        :show-modal="!!createModalOpened"
        modal-class="medium-modal"
        title="Add Tax Template"
        @close-click="closeCreateModal"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="storeTemplate">
                <div class="col-md-6">
                    <VInput
                        id="tax_template_name"
                        v-model="form.name"
                        label="Name"
                        placeholder="e.g. VAT 13%, TDS on Rent"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="tax_template_rate"
                        v-model="form.rate"
                        input-type="number"
                        label="Rate (%)"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="tax_template_type"
                        v-model="form.type"
                        label="Type"
                        placeholder="Type"
                        :options="typeOptions"
                        value-prop="value"
                        name-prop="label"
                    />
                </div>
                <div v-if="form.type === 'tds'" class="col-md-6">
                    <VSelect
                        id="tax_template_tds_category"
                        v-model="form.tds_category"
                        label="TDS Category"
                        placeholder="TDS Category"
                        :options="tdsCategoryOptions"
                        value-prop="value"
                        name-prop="label"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="tax_template_description"
                        v-model="form.description"
                        label="Description"
                        :rows="2"
                    />
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input
                            id="tax_template_is_default"
                            v-model="form.is_default"
                            class="form-check-input"
                            type="checkbox"
                        >
                        <label class="form-check-label" for="tax_template_is_default">
                            Seed to new companies by default
                        </label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-danger me-1" type="button" @click="closeCreateModal">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { apiSuperAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import { tdsCategoryOptions, typeOptions } from './options.js';

const createModalOpened = defineModel('createModalOpened');
const emit = defineEmits(['saved']);

const initialState = {
    name: '',
    rate: 0,
    type: '',
    tds_category: '',
    is_default: true,
    description: '',
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

const storeTemplate = async () => {
    isSubmitting.value = true;
    try {
        await apiSuperAdmin('tax-template', 'post', {
            ...form,
            type: form.type || null,
            tds_category: form.tds_category || null,
        });
        toast('success', 'Template created.');
        closeCreateModal();
        emit('saved');
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeCreateModal = () => {
    Object.assign(form, { ...initialState });
    createModalOpened.value = false;
};
</script>
