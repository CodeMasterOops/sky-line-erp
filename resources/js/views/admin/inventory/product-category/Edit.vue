<template>
    <VModal
        :show-modal="!!edit_product_category_id"
        @close-click="closeEditModal"
        title="Edit Product Category">
        <template #modal-body>
            <VLoader v-if="productCategory.loading" loader-type="progress"/>
            <form @submit.prevent="updateProductCategory(productCategory.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VMultiselect
                        id="parent_id"
                        v-model="form.parent_id"
                        :options="parentOptionsTree"
                        label="Parent category"
                        placeholder="category"
                        @validate="validateField('parent_id')"
                        :error="errors.parent_id"
                    />
                </div>
                <div class="col-md-6">
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
import {computed, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string, mixed} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useProductCategoryStore} from '@/stores/admin/inventory/product-category.js';
import {buildCategoryOptionsTree, collectDescendantIds} from '@/helpers/categoryTree.js';

const categoryStore = useProductCategoryStore();

const edit_product_category_id = defineModel('product_category_id');

const {productCategory, productCategories} = storeToRefs(categoryStore);

const parentOptionsTree = computed(() => {
    const id = edit_product_category_id.value;
    if (!id) {
        return [];
    }
    const n = Number(id);
    const ex = new Set([n, ...collectDescendantIds(productCategories.value.data, n)]);
    return buildCategoryOptionsTree(productCategories.value.data, ex);
});

const initialState = {
    parent_id: '',
    name: '',
    description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_product_category_id.value, async (id) => {
    if (id) {
        await categoryStore.getProductCategories();
        await categoryStore.getProductCategory(id);
        Object.keys(form).forEach((key) => {
            const v = productCategory.value.data[key];
            form[key] = key === 'parent_id' ? (v ?? '') : (v || '');
        });
    }
});

const validations = object({
    parent_id: mixed().nullable(),
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
