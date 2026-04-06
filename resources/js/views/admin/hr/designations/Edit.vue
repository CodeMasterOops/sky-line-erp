<template>
    <VModal :show-modal="!!editId" @close-click="closeModal" title="Edit Designation">
        <template #modal-body>
            <VLoader v-if="loading" loader-type="progress" />
            <form @submit.prevent="submit" class="row g-3">
                <div class="col-12">
                    <VInput id="name" v-model="form.name" label="Name" @validate="validateField('name')" :error="errors.name" />
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" v-model="form.description" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="form.is_active" />
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="closeModal" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { apiAdmin } from '@/helpers/api.js';
import { useDesignationStore } from '@/stores/admin/hr/designation.js';

const store = useDesignationStore();
const editId = defineModel('editId');
const initial = { name: '', description: '', is_active: true };
const form = reactive({ ...initial });
const isSubmitting = ref(false);
const loading = ref(false);
const validations = object({ name: string().required('Name is required.') });
const { errors, validateField, validateForm } = useYup(form, validations);

watch(() => editId.value, async (id) => {
    if (id) {
        loading.value = true;
        try { const res = await apiAdmin(`hr/designation/${id}`); Object.keys(form).forEach(k => { form[k] = res.data.data[k] ?? initial[k]; }); }
        finally { loading.value = false; }
    }
});

const submit = async () => {
    if (await validateForm(validations, form)) {
        isSubmitting.value = true;
        try { const res = await store.updateDesignation(editId.value, form); toast(res.status, res.data.message); closeModal(); }
        catch (e) { showErrors(e); }
        finally { isSubmitting.value = false; }
    }
};
const closeModal = () => { Object.assign(form, { ...initial }); errors.value = {}; editId.value = ''; };
</script>
