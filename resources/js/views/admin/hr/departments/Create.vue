<template>
    <VModal :show-modal="!!createModalOpened" @close-click="createModalOpened = false" title="Add Department">
        <template #modal-body>
            <form @submit.prevent="submit" class="row g-3">
                <div class="col-md-8">
                    <VInput id="name" v-model="form.name" label="Name" @validate="validateField('name')" :error="errors.name" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="code">Code</label>
                    <div class="input-group">
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            class="form-control"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="codeLoading"
                            @click="fetchNextCode">
                            Generate
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" v-model="form.description" rows="2"></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="closeModal" class="btn btn-cancel">Cancel</button>
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
import { useNextCode } from '@/helpers/useNextCode.js';
import { useDepartmentStore } from '@/stores/admin/hr/department.js';

const store = useDepartmentStore();
const createModalOpened = defineModel('createModalOpened');

const initial = { name: '', code: '', description: '' };
const form = reactive({ ...initial });
const isSubmitting = ref(false);
const validations = object({ name: string().required('Name is required.') });
const { errors, validateField, validateForm } = useYup(form, validations);
const { loading: codeLoading, fetchNextCode } = useNextCode(form, 'code', 'hr/department/next-code');

watch(createModalOpened, async (open) => {
    if (open) {
        await fetchNextCode();
    }
});

const submit = async () => {
    if (await validateForm(validations, form)) {
        isSubmitting.value = true;
        try {
            const res = await store.storeDepartment(form);
            toast(res.status, res.data.message);
            closeModal();
        } catch (e) { showErrors(e); }
        finally { isSubmitting.value = false; }
    }
};

const closeModal = () => { Object.assign(form, { ...initial }); errors.value = {}; createModalOpened.value = false; };
</script>
