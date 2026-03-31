<template>
    <VModal :show-modal="!!createModalOpened" @close-click="createModalOpened = false" title="Add Designation">
        <template #modal-body>
            <form @submit.prevent="submit" class="row g-3">
                <div class="col-12">
                    <VInput id="name" v-model="form.name" label="Name" @validate="validateField('name')" :error="errors.name" />
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" v-model="form.description" rows="2"></textarea>
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
import { reactive, ref } from 'vue';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { useDesignationStore } from '@/stores/admin/hr/designation.js';

const store = useDesignationStore();
const createModalOpened = defineModel('createModalOpened');
const initial = { name: '', description: '' };
const form = reactive({ ...initial });
const isSubmitting = ref(false);
const validations = object({ name: string().required('Name is required.') });
const { errors, validateField, validateForm } = useYup(form, validations);

const submit = async () => {
    if (await validateForm(validations, form)) {
        isSubmitting.value = true;
        try { const res = await store.storeDesignation(form); toast(res.status, res.data.message); closeModal(); }
        catch (e) { showErrors(e); }
        finally { isSubmitting.value = false; }
    }
};
const closeModal = () => { Object.assign(form, { ...initial }); errors.value = {}; createModalOpened.value = false; };
</script>
