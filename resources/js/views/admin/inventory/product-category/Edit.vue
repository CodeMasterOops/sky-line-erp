<template>
    <VModal
        :show-modal="!!edit_product_category_id"
        @close-click="closeEditModal"
        title="Edit Product Category">
        <template #modal-body>
            <VLoader v-if="productCategory.loading" loader-type="progress"/>
            <form @submit.prevent="updateProductCategory(productCategory.data.id)" class="row g-3">
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
                    <button @click="closeEditModal" class="btn btn-danger me-2" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useProductCategoryStore} from '@/stores/admin/inventory/product-category.js';

const categoryStore = useProductCategoryStore();

const edit_product_category_id = defineModel('product_category_id');

const {productCategory} = storeToRefs(categoryStore);

const initialState = {
    name: '',
    description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_product_category_id.value, async (id) => {
    if (id) {
        await categoryStore.getProductCategory(id);
        Object.keys(form).forEach(key => {
            form[key] = productCategory.value.data[key] || '';
        });
    }
});

const validations = object({
    name: string().required('Name is required.'),
    description: string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateProductCategory = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await categoryStore.updateProductCategory(id, form);
            toast(res.status, res.data.message);
            closeEditModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeEditModal = () => {
    resetForm();
    edit_product_category_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
