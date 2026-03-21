<template>
    <VModal :show-modal="!!createModalOpened" @close-click="createModalOpened = false" modal-class="large-modal"
            title="Add New Product">

        <template #modal-body>
            <form @submit.prevent="storeProduct" class="row g-3">
                <div v-for="type in productTypes" :key="type.id" class="col-6">
                    <input type="radio" v-model="form.product_type" :value="type.id" :id="type.id" class="btn-check">
                    <label :for="type.id" class="btn w-100 py-2 rounded-1 btn-outline-secondary">
                        {{ type.name }}
                    </label>
                </div>
                <div class="col-md-6">
                    <VInput id="name" v-model="form.name" label="Name" @validate="validateField('name')"
                            :error="errors.name"/>
                </div>
                <div class="col-md-6">
                    <VInput id="code" v-model="form.code" label="Code" @validate="validateField('code')"
                            :error="errors.code"/>
                </div>
                <div class="col-md-6">
                    <VMultiselect id="product_category_id" v-model="form.product_category_id"
                                  :options="productCategories.data" label="Category"
                                  @validate="validateField('product_category_id')" :error="errors.product_category_id"/>
                </div>
                <div class="col-md-6">
                    <VMultiselect id="unit_id" v-model="form.unit_id" :options="units.data" label="Unit"
                                  @validate="validateField('unit_id')" :error="errors.unit_id"/>
                </div>
                <div class="col-md-6">
                    <VMultiselect id="brand_id" v-model="form.brand_id" :options="brands.data" label="Brand"
                                  @validate="validateField('brand_id')" :error="errors.brand_id"/>
                </div>
                <div class="col-md-6">
                    <VInput input-type="number" id="reorder_quantity" v-model="form.reorder_quantity"
                            label="Reorder Qty" @validate="validateField('reorder_quantity')"
                            :error="errors.reorder_quantity"/>
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="has_variants"
                        v-model="form.has_variants"
                        :options="[{id:1,name:'Yes'},{id:0,name:'No'}]"
                        label="Has Variants?"
                    />
                </div>
                <template v-if="form.has_variants==='1'">
                    <div class="border p-3">
                        <div v-for="(variant,index) in selectedVariants" :key="index" class="row my-1">
                            <div class="col-md-4">
                                <VSelect
                                    v-model="selectedVariants[index].attribute_id"
                                    :options="selectableAttributes(variant.attribute_id)"
                                    placeholder="Attribute"
                                />
                            </div>
                            <div class="col-md-6">
                                <VMultiselect
                                    v-model="selectedVariants[index].values"
                                    mode="multiple"
                                    :options="attributeValues(variant.attribute_id)"
                                    name-prop="value"
                                    placeholder="Value"
                                />
                            </div>
                            <div class="col-md-2">
                                <button type="button" @click="removeVariantOption(index)"
                                        class="btn btn-sm btn-outline-danger">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button v-if="selectedVariants.length!==attributes.data.length" type="button"
                                @click="addVariantOption" class="btn btn-sm btn-outline-secondary mt-2">
                            Add Option
                        </button>
                    </div>
                    <div v-if="form.variants.length" class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th style="width:40px;">SN</th>
                                <th v-for="attr in selectedAttributes" :key="attr.id">
                                    {{ attr.attr_name }}
                                </th>
                                <th style="width:130px;">SKU</th>
                                <th style="width:130px;">Purchase Price</th>
                                <th style="width:130px;">Sales Price</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(variant,index) in form.variants" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td v-for="value in variant.value_labels" :key="value">
                                    {{ value }}
                                </td>
                                <td>
                                    <VInput
                                        v-model="form.variants[index].sku"
                                        placeholder="SKU"
                                        input-class="form-control form-control-sm"
                                        @validate="validateField(`variants[${index}].sku`)"
                                        :error="errors[`variants[${index}].sku`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.variants[index].purchase_price"
                                        placeholder="Purchase Price"
                                        input-class="form-control form-control-sm"
                                        @validate="validateField(`variants[${index}].purchase_price`)"
                                        :error="errors[`variants[${index}].purchase_price`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.variants[index].sales_price"
                                        placeholder="Sales Price"
                                        input-class="form-control form-control-sm"
                                        @validate="validateField(`variants[${index}].sales_price`)"
                                        :error="errors[`variants[${index}].sales_price`]"
                                    />
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
                <template v-else-if="form.variants.length">
                    <div class="col-md-6">
                        <VInput
                            id="sku"
                            v-model="form.variants[0].sku"
                            label="SKU"
                            @validate="validateField(`variants[0].sku`)"
                            :error="errors[`variants[0].sku`]"
                        />
                    </div>
                    <div class="col-md-6">
                        <VInput
                            input-type="number"
                            id="purchase_price"
                            v-model="form.variants[0].purchase_price"
                            label="Purchase Price"
                            @validate="validateField(`variants[0].purchase_price`)"
                            :error="errors[`variants[0].purchase_price`]"
                        />
                    </div>
                    <div class="col-md-6">
                        <VInput
                            input-type="number"
                            id="sales_price"
                            v-model="form.variants[0].sales_price"
                            label="Sales Price"
                            @validate="validateField(`variants[0].sales_price`)"
                            :error="errors[`variants[0].sales_price`]"
                        />
                    </div>
                </template>
                <div class="col-md-12">
                    <VTextarea id="description" v-model="form.description" label="Description"
                               @validate="validateField('description')" :error="errors.description"/>
                </div>
                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {onMounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';
import {useProductCategoryStore} from '@/stores/admin/inventory/product-category.js';
import {useBrandStore} from '@/stores/admin/inventory/brand.js';
import {storeToRefs} from 'pinia';
import {useAttributeStore} from "@/stores/admin/inventory/attribute.js";

const productStore = useProductStore();

const createModalOpened = defineModel('createModalOpened');

const unitStore = useUnitStore();
const categoryStore = useProductCategoryStore();
const brandStore = useBrandStore();
const attributeStore = useAttributeStore();

onMounted(() => {
    unitStore.getUnits();
    categoryStore.getProductCategories();
    brandStore.getBrands();
    attributeStore.getAttributes();
});

const {units} = storeToRefs(unitStore);
const {productCategories} = storeToRefs(categoryStore);
const {brands} = storeToRefs(brandStore);
const {attributes} = storeToRefs(attributeStore);
const productTypes = [
    {id: 'product', name: 'Product'},
    {id: 'service', name: 'Service'}
];

const initialState = {
    product_category_id: '',
    product_type: 'product',
    name: '',
    code: '',
    image: '',
    unit_id: '',
    brand_id: '',
    reorder_quantity: '',
    description: '',
    has_variants: 0,
    variants: [],
    attribute_values: [],
    track_stock: 0,
};

watch(() => createModalOpened.value, (opened) => {
    if (opened) {
        addVariants();
    }
})

const form = reactive({...initialState});
const isSubmitting = ref(false);

const addVariants = () => {
    form.variants.push({
        sku: '',
        sales_price: '',
        purchase_price: '',
        is_default: false,
    })
}

const selectedVariants = ref([]);

const addVariantOption = () => {
    selectedVariants.value.push({
        attribute_id: '',
        values: []
    })
}

const removeVariantOption = (index) => {
    selectedVariants.value.splice(index, 1);
}

const selectedAttributeOptions = ref([]);

const selectableAttributes = (attrId = null) => {
    let attrIds = selectedVariants.value.filter(v => v.attribute_id).map(v => parseInt(v.attribute_id));
    if (attrId) {
        return attributes.value.data.filter(a =>
            !attrIds.includes(a.id) || a.id === parseInt(attrId)
        )
    }
    if (attrIds.length) {
        return attributes.value.data.filter(a => !attrIds.includes(a.id));
    }
    return attributes.value.data;
}

const attributeValues = (attrId = null, valId = null) => {
    if (attrId) {
        const attrValues = attributes.value.data.find(a => a.id == attrId).values;
        if (valId) {
            return attrValues.find(v => v.id === valId);
        }
        return attributes.value.data.find(a => a.id == attrId).values;
    }
    return [];
}

const selectedAttributes = ref([]);

const cartesianProduct = (arr) => {
    return arr.reduce((acc, curr) => {
        return acc.flatMap(a => curr.map(b => [...a, b]))
    }, [[]])
}

watch(() => selectedVariants.value, () => {
    const variantValueGroups = selectedVariants.value
        .filter(v => v.attribute_id && v.values.length)
        .map(v =>
            v.values.map(val => ({
                attr_id: parseInt(v.attribute_id),
                value_id: parseInt(val)
            }))
        )

    if (!variantValueGroups.length) {
        form.variants = [];
        return
    }

    const combinations = cartesianProduct(variantValueGroups);
    let cList = [];

    let attrList = [];

    combinations.forEach((cmb, index) => {
        cmb.forEach(l => {
            let check = attrList.find(a => a.attr_id === l.attr_id);
            if (!check) {
                const attr = attributes.value.data.find(d => d.id === l.attr_id);
                attrList.push({
                    attr_id: attr.id,
                    attr_name: attr.name
                })
            }
        })
        cList.push({
            value_labels: cmb.map(attr => attributeValues(attr.attr_id, attr.value_id)?.value || ''),
            sku: '',
            sales_price: '',
            purchase_price: '',
            is_default: index === 0,
            attribute_values: cmb.map(a => a.value_id)
        })
    })

    selectedAttributes.value = attrList;
    form.variants = cList;
}, {deep: true})

watch(() => selectedAttributeOptions.value, (attrOptions) => {
    let values = [];
    attrOptions.forEach(attr => {
        attr.values.forEach(v => {
            if (!values.includes(v)) {
                values.push(v);
            }
        })
    })
    form.attribute_values = values;
}, {deep: true})

watch(() => form.has_variants, (has_variants) => {
    form.variants.splice(0);
    if (!has_variants) {
        addVariants();
    }
})

const validations = object({
    product_type: string().required('Product Type is required.'),
    product_category_id: string().required('Category is required.'),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    unit_id: string().required('Unit is required.'),
    brand_id: string().nullable(),
    reorder_quantity: string().required('Reorder qty is required.'),
    description: string().nullable(),
    variants: array().of(
        object({
            sku: string().nullable(),
            sales_price: string().required('Sales price is required.'),
            purchase_price: string().required('Purchase price is required.'),
        })
    ),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeProduct = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await productStore.storeProduct(form);
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
    Object.assign(form, {...initialState});
    form.variants.splice(0);
    errors.value = {};
}

</script>
