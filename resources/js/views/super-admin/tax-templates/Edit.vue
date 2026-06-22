<template>
    <VModal
        :show-modal="!!templateId"
        modal-class="medium-modal"
        title="Edit Tax Template"
        @close-click="closeEditModal"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="updateTemplate">
                <div class="col-md-6">
                    <VInput
                        id="edit_tax_template_name"
                        v-model="form.name"
                        label="Name"
                        placeholder="e.g. VAT 13%, TDS on Rent"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="edit_tax_template_rate"
                        v-model="form.rate"
                        input-type="number"
                        label="Rate (%)"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="edit_tax_template_type"
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
                        id="edit_tax_template_tds_category"
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
                        id="edit_tax_template_description"
                        v-model="form.description"
                        label="Description"
                        :rows="2"
                    />
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input
                            id="edit_tax_template_is_default"
                            v-model="form.is_default"
                            class="form-check-input"
                            type="checkbox"
                        >
                        <label class="form-check-label" for="edit_tax_template_is_default">
                            Seed to new companies by default
                        </label>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-cancel" type="button" @click="closeEditModal">
                        Cancel
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { apiSuperAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import { tdsCategoryOptions, typeOptions } from './options.js';

const templateId = defineModel('templateId');
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

watch(templateId, async (id) => {
    if (!id) {
        return;
    }
    try {
        const res = await apiSuperAdmin(`tax-template/${id}`, 'get');
        const data = res.data.data;
        form.name = data.name ?? '';
        form.rate = data.rate ?? 0;
        form.type = data.type ?? '';
        form.tds_category = data.tds_category ?? '';
        form.is_default = data.is_default ?? true;
        form.description = data.description ?? '';
    } catch (e) {
        showErrors(e);
    }
});

const updateTemplate = async () => {
    if (!templateId.value) {
        return;
    }
    isSubmitting.value = true;
    try {
        await apiSuperAdmin(`tax-template/${templateId.value}`, 'put', {
            ...form,
            type: form.type || null,
            tds_category: form.tds_category || null,
        });
        toast('success', 'Template updated.');
        closeEditModal();
        emit('saved');
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeEditModal = () => {
    Object.assign(form, { ...initialState });
    templateId.value = '';
};
</script>
