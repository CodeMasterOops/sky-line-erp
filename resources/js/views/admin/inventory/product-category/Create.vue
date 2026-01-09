<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        title="Add Product Category">
        <template #modal-body>
            <form @submit.prevent="storeProductCategory" class="row g-3">
                <div class="col-md-12">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-12">
                    <VTextarea
                        id="description"
                        v-model="form.description"
                        label="Description"
                        @validate="validateField('description')"
                        :error="errors.description"
                    />
                </div>
                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger" type="button">
                        Close
                    </button>
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
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';

const categoryStore = useProductCategoryStore();

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    name: '',
    description: ''
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Name is required.'),
    description: string().nullable()
});

const { errors, validateField, validateForm } = useYup(form, validations);

const storeProductCategory = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await categoryStore.storeProductCategory(form);
            toast(res.status, res.data.message);
            closeCreateModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeCreateModal = () => {
    resetForm();
    createModalOpened.value = false;
};

function resetForm() {
    Object.assign(form, { ...initialState });
    errors.value = {};
}

</script>
