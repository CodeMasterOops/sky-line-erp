<template>
    <VModal
        :show-modal="!!edit_product_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Product">
        <template #modal-body>
            <VLoader v-if="product.loading" loader-type="progress" />
            <form @submit.prevent="updateProduct(product.data.id)" class="row g-3">
                <div v-for="type in productTypes" :key="type.id" class="col-6">
                    <input type="radio" v-model="form.product_type" :value="type.id" :id="type.id" class="btn-check">
                    <label :for="type.id" class="btn w-100 py-2 rounded-1 btn-outline-secondary">
                        {{ type.name }}
                    </label>
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
                <div class="col-md-6">
                    <VInput
                        id="code"
                        v-model="form.code"
                        label="Code"
                        @validate="validateField('code')"
                        :error="errors.code"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="product_category_id"
                        v-model="form.product_category_id"
                        :options="productCategories.data"
                        label="Category"
                        @validate="validateField('product_category_id')"
                        :error="errors.product_category_id"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="unit_id"
                        v-model="form.unit_id"
                        :options="units.data"
                        label="Unit"
                        @validate="validateField('unit_id')"
                        :error="errors.unit_id"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="brand_id"
                        v-model="form.brand_id"
                        :options="brands.data"
                        label="Brand"
                        @validate="validateField('brand_id')"
                        :error="errors.brand_id"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="sku"
                        v-model="form.sku"
                        label="SKU"
                        @validate="validateField('sku')"
                        :error="errors.sku"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        input-type="number"
                        id="reorder_quantity"
                        v-model="form.reorder_quantity"
                        label="Reorder Qty"
                        @validate="validateField('reorder_quantity')"
                        :error="errors.reorder_quantity"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        input-type="number"
                        id="purchase_price"
                        v-model="form.purchase_price"
                        label="Purchase Price"
                        @validate="validateField('purchase_price')"
                        :error="errors.purchase_price"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        input-type="number"
                        id="sales_price"
                        v-model="form.sales_price"
                        label="Sales Price"
                        @validate="validateField('sales_price')"
                        :error="errors.sales_price"
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
                    <button @click="closeEditModal" class="btn btn-danger" type="button">
                        Close
                    </button>
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
import { storeToRefs } from 'pinia';
import { useProductStore } from '@/stores/admin/inventory/product.js';
import { useUnitStore } from '@/stores/admin/inventory/unit.js';
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';
import { useBrandStore } from '@/stores/admin/inventory/brand.js';

const productStore = useProductStore();
const unitStore = useUnitStore();
const categoryStore = useProductCategoryStore();
const brandStore = useBrandStore();

const edit_product_id = defineModel('product_id');

const { product } = storeToRefs(productStore);
const { units } = storeToRefs(unitStore);
const { productCategories } = storeToRefs(categoryStore);
const { brands } = storeToRefs(brandStore);
const productTypes = [
    { id: 'product', name: 'Product' },
    { id: 'service', name: 'Service' }
];

const initialState = {
    product_category_id: '',
    product_type: 'product',
    name: '',
    code: '',
    sku: '',
    image: '',
    unit_id: '',
    brand_id: '',
    sales_price: '',
    purchase_price: '',
    reorder_quantity: '',
    description: ''
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

watch(() => edit_product_id.value, async (id) => {
    if (id) {
        await productStore.getProduct(id);
        Object.keys(form).forEach(key => {
            form[key] = product.value.data[key] || '';
        });
    }
});

const validations = object({
    product_type: string().required('Product Type is required.'),
    product_category_id: string().required('Category is required.'),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    sku: string().nullable(),
    unit_id: string().required('Unit is required.'),
    brand_id: string().nullable(),
    sales_price: string().required('Sales price is required.'),
    purchase_price: string().required('Purchase price is required.'),
    reorder_quantity: string().required('Reorder qty is required.'),
    description: string().nullable()
});

const { errors, validateField, validateForm } = useYup(form, validations);

const updateProduct = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await productStore.updateProduct(id, form);
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
    edit_product_id.value = '';
};

function resetForm() {
    Object.assign(form, { ...initialState });
    errors.value = {};
}

</script>
