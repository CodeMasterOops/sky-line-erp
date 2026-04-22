<template>
    <VLoader v-if="isEdit && !ready" loader-type="progress" />
    <form v-else @submit.prevent="submitProduct" class="add-product-form">
        <div class="accordions-items-seperate" id="productFormAccordion">
            <!-- Product information -->
            <div class="accordion-item border mb-4">
                <h2 class="accordion-header" id="headingProductInfo">
                    <button class="accordion-button collapsed bg-white" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseProductInfo" aria-expanded="true" aria-controls="collapseProductInfo">
                        <span class="d-flex align-items-center gap-2 text-start">
                            <span class="fw-semibold">Product information</span>
                        </span>
                    </button>
                </h2>
                <div id="collapseProductInfo" class="accordion-collapse collapse show"
                    aria-labelledby="headingProductInfo">
                    <div class="accordion-body border-top">
                        <div class="row g-3">
                            <div class="col-12">
                                <div>
                                    <label class="form-label">
                                        What are you adding?
                                        <span class="text-danger ms-1">*</span>
                                    </label>
                                    <p class="text-muted small mb-3">
                                        Pick whether this is a stocked item or a billable service. Services always use
                                        one
                                        price row; products can be simple or variable in the next section.
                                    </p>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <button type="button"
                                                class="w-100 text-start p-3 rounded-3 border bg-white product-pricing-card"
                                                :class="{ 'border-primary border-2 shadow-sm': form.product_type === 'product' }"
                                                @click="setProductType('product')">
                                                <div class="fw-semibold text-dark">
                                                    <i class="ti ti-package me-1 text-primary"></i>
                                                    Product
                                                </div>
                                                <div class="small text-muted mt-1 mb-0">
                                                    Stocked goods you sell; supports simple or variable pricing below.
                                                </div>
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button type="button"
                                                class="w-100 text-start p-3 rounded-3 border bg-white product-pricing-card"
                                                :class="{ 'border-primary border-2 shadow-sm': form.product_type === 'service' }"
                                                @click="setProductType('service')">
                                                <div class="fw-semibold text-dark">
                                                    <i class="ti ti-briefcase me-1 text-primary"></i>
                                                    Service
                                                </div>
                                                <div class="small text-muted mt-1 mb-0">
                                                    Labor, time, or fees; one SKU and one price row (no variants).
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="errors.product_type" class="text-danger small mt-1">
                                        {{ errors.product_type }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <VInput id="name" v-model="form.name" label="Name" @validate="validateField('name')"
                                    :error="errors.name" />
                            </div>
                            <div class="col-sm-6 col-12">
                                <div class="list position-relative">
                                    <label class="form-label" for="code">
                                        Product code
                                        <span class="text-danger ms-1">*</span>
                                    </label>
                                    <input id="code" v-model="form.code" type="text"
                                        placeholder="e.g. internal or supplier code" class="form-control list"
                                        :class="{ 'is-invalid': errors.code }" autocomplete="off"
                                        @blur="validateField('code')">
                                    <button type="button" class="btn btn-primaryadd" @click="generateProductCode">
                                        Generate
                                    </button>
                                    <div v-if="errors.code" class="invalid-feedback d-block">
                                        {{ errors.code }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <VMultiselect id="product_category_id" v-model="form.product_category_id"
                                    :options="productCategories.data" label="Category"
                                    @validate="validateField('product_category_id')"
                                    :error="errors.product_category_id" />
                            </div>
                            <div class="col-md-6">
                                <VMultiselect id="unit_id" v-model="form.unit_id" :options="units.data" label="Unit"
                                    @validate="validateField('unit_id')" :error="errors.unit_id" />
                            </div>
                            <div class="col-md-6">
                                <VMultiselect id="brand_id" v-model="form.brand_id" :options="brands.data" label="Brand"
                                    @validate="validateField('brand_id')" :error="errors.brand_id" />
                            </div>
                            <div v-if="isPhysicalProduct" class="col-md-6">
                                <div class="mb-3 list position-relative">
                                    <label class="form-label" for="product-item-barcode">
                                        Item barcode
                                        <span class="text-danger ms-1">*</span>
                                    </label>
                                    <input id="product-item-barcode" v-model="itemBarcode" type="text"
                                        class="form-control list" placeholder="Scan or generate" autocomplete="off">
                                    <button type="button" class="btn btn-primaryadd" @click="generateItemBarcode">
                                        Generate
                                    </button>
                                </div>
                            </div>

                            <div v-if="isPhysicalProduct" class="col-md-6">
                                <VInput input-type="number" id="reorder_quantity" v-model="form.reorder_quantity"
                                    label="Reorder quantity" @validate="validateField('reorder_quantity')"
                                    :error="errors.reorder_quantity" />
                            </div>
                            <div class="col-12">
                                <VTextarea id="description" v-model="form.description" label="Description"
                                    @validate="validateField('description')" :error="errors.description" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing & inventory -->
            <div class="accordion-item border mb-4">
                <h2 class="accordion-header" id="headingPricing">
                    <button class="accordion-button collapsed bg-white" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapsePricing" aria-expanded="true" aria-controls="collapsePricing">
                        <span class="d-flex align-items-center gap-2 text-start">
                            <span class="fw-semibold">Pricing &amp; inventory</span>
                        </span>
                    </button>
                </h2>
                <div id="collapsePricing" class="accordion-collapse collapse show" aria-labelledby="headingPricing">
                    <div class="accordion-body border-top">
                        <p v-if="isPhysicalProduct" class="alert alert-light border small mb-3 mb-md-4 text-dark">
                            <i class="ti ti-info-circle me-1" />
                            <strong>Pricing and COGS.</strong>
                            The amounts below are your default purchase and selling prices. Actual
                            <strong>inventory cost</strong> is recorded when you receive stock (for example
                            on an <strong>approved purchase bill</strong> or stock adjustment). On-hand quantity is
                            never stored here. When you sell, cost of goods uses your company&rsquo;s inventory method
                            (currently
                            <strong>{{ inventoryCostingMethodName }}</strong> — change under
                            <router-link :to="{ name: 'admin.general-settings' }" class="text-primary">General settings</router-link>).
                        </p>
                        <p v-else class="text-muted small mb-3">
                            Set default purchase and selling prices for quoting; services are not held as stock.
                        </p>
                        <div v-if="isPhysicalProduct" class="row g-3 mb-1">
                            <div class="col-12">
                                <label class="form-label mb-1">Pricing model</label>
                                <p class="text-muted small mb-3">
                                    Simple items use one SKU and price. Variable items use options (for example size and
                                    color) and get one row per combination.
                                </p>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <button type="button"
                                            class="w-100 text-start p-3 rounded-3 border bg-white product-pricing-card"
                                            :class="{ 'border-primary border-2 shadow-sm': !form.has_variants }"
                                            @click="setPricingModel(false)">
                                            <div class="fw-semibold text-dark">
                                                <i class="ti ti-box me-1 text-primary"></i>
                                                Simple product
                                            </div>
                                            <div class="small text-muted mt-1 mb-0">
                                                One SKU with default purchase and selling prices.
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="button"
                                            class="w-100 text-start p-3 rounded-3 border bg-white product-pricing-card"
                                            :class="{ 'border-primary border-2 shadow-sm': form.has_variants }"
                                            @click="setPricingModel(true)">
                                            <div class="fw-semibold text-dark">
                                                <i class="ti ti-layout-grid me-1 text-primary"></i>
                                                Variable product
                                            </div>
                                            <div class="small text-muted mt-1 mb-0">
                                                Define options; each combination becomes a variant with its own SKU and
                                                prices.
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="mb-3">
                            <p class="text-muted small mb-0">
                                Services use a single SKU and price. Variant options are not available.
                            </p>
                        </div>

                        <template v-if="isVariableProduct">
                            <div class="variant-builder rounded-3 border bg-light bg-opacity-50 p-3 mt-3">
                                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                                    <div>
                                        <span class="fw-medium text-dark d-block">
                                            <i class="ti ti-adjustments-horizontal me-1"></i>
                                            Variant options
                                        </span>
                                        <span class="small text-muted">
                                            Add one row per option (for example Color). Customers pick one value per
                                            option; all combinations are generated below.
                                        </span>
                                    </div>
                                </div>

                                <div v-if="!hasAttributesConfigured" class="alert alert-light border mb-0">
                                    <div
                                        class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                        <span class="small mb-0">
                                            No attributes are set up yet. Create attributes and values (for example
                                            Size: S, M, L) before building variants.
                                        </span>
                                        <router-link :to="{ name: 'admin.variant-attributes' }"
                                            class="btn btn-sm btn-outline-primary text-nowrap">
                                            Open attributes
                                        </router-link>
                                    </div>
                                </div>

                                <template v-else>
                                    <div v-for="(variant, index) in selectedVariants" :key="index"
                                        class="card border mb-3 mb-md-2 shadow-none">
                                        <div class="card-body py-3">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="small fw-semibold text-secondary text-uppercase">Option {{
                                                    index + 1 }}</span>
                                                <button type="button" @click="removeVariantOption(index)"
                                                    class="btn btn-sm btn-link text-danger p-0 lh-1" title="Remove option">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-12 col-lg-5">
                                                    <label class="form-label small mb-1">Option name</label>
                                                    <VSelect :model-value="selectedVariants[index].attribute_id"
                                                        :options="selectableAttributes(variant.attribute_id)"
                                                        placeholder="Select attribute"
                                                        @update:model-value="(val) => setVariantOptionAttribute(index, val)" />
                                                </div>
                                                <div class="col-12">
                                                    <div
                                                        class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                        <label class="form-label small mb-0">Values</label>
                                                        <div v-if="selectedVariants[index].attribute_id && attributeValues(selectedVariants[index].attribute_id).length"
                                                            class="btn-group btn-group-sm ms-auto">
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                @click="selectAllVariantValues(index)">
                                                                All
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                @click="clearVariantValues(index)">
                                                                Clear
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p v-if="!selectedVariants[index].attribute_id"
                                                        class="small text-muted mb-0">
                                                        Select an option name to see values.
                                                    </p>
                                                    <div v-else-if="!attributeValues(selectedVariants[index].attribute_id).length"
                                                        class="small text-muted mb-0">
                                                        No values for this attribute. Add values in variant attributes.
                                                    </div>
                                                    <div v-else
                                                        class="d-flex flex-wrap gap-2 gap-md-3 border rounded-2 px-2 py-1 bg-white">
                                                        <div v-for="val in attributeValues(selectedVariants[index].attribute_id)"
                                                            :key="val.id" class="form-check">
                                                            <input class="form-check-input" type="checkbox"
                                                                :id="'va-' + index + '-' + val.id"
                                                                :checked="isVariantValueSelected(index, val.id)"
                                                                @change="toggleVariantValue(index, val.id, $event.target.checked)">
                                                            <label class="form-check-label small"
                                                                :for="'va-' + index + '-' + val.id">
                                                                {{ val.value }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button v-if="selectedVariants.length !== attributes.data.length" type="button"
                                        @click="addVariantOption" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-plus me-1" />
                                        Add another option
                                    </button>
                                </template>
                            </div>

                            <div v-if="form.variants.length" class="mt-4">
                                <div
                                    class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 mb-3 border-bottom">
                                    <p class="mb-0 small fw-medium text-dark align-middle">
                                        <i class="ti ti-stack-2 me-1 text-primary"></i>
                                        {{ form.variants.length }} variant{{ form.variants.length === 1 ? '' : 's' }}
                                        {{ isEdit ? 'will be saved' : 'will be created' }}
                                    </p>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            :disabled="!form.code?.trim()" @click="applySkuPrefixFromCode">
                                            Fill SKU from code
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            :disabled="form.variants.length < 2" @click="applyFirstPurchaseToAll">
                                            Copy first purchase to all
                                        </button>
                                    </div>
                                </div>
                                <div class="modal-body-table border variant-skus-wrap" id="variant-table">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="text-center text-muted variant-table-col-index">#</th>
                                                    <th v-for="attr in variantColumnAttributes" :key="attr.attr_id">
                                                        {{ attr.attr_name }}
                                                    </th>
                                                    <th>SKU</th>
                                                    <th class="text-end" title="Default purchase; actual cost comes from received stock">
                                                        Purchase Price (Default)</th>
                                                    <th class="text-end" title="Default selling price for sales and purchase screens">
                                                        Selling Price</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(variant, index) in form.variants" :key="index">
                                                    <td class="text-center text-muted small">{{ index + 1 }}</td>
                                                    <td v-for="value in variant.value_labels"
                                                        :key="String(value) + index">
                                                        {{ value }}
                                                    </td>
                                                    <td>
                                                        <VInput v-model="form.variants[index].sku" placeholder="SKU"
                                                            input-class="form-control form-control-sm py-1"
                                                            @validate="validateField(`variants[${index}].sku`)"
                                                            :error="errors[`variants[${index}].sku`]" />
                                                    </td>
                                                    <td class="text-end">
                                                        <VInput input-type="number"
                                                            v-model="form.variants[index].purchase_price"
                                                            placeholder="0"
                                                            input-class="form-control form-control-sm py-1 text-end"
                                                            @validate="validateField(`variants[${index}].purchase_price`)"
                                                            :error="errors[`variants[${index}].purchase_price`]" />
                                                    </td>
                                                    <td class="text-end">
                                                        <VInput input-type="number"
                                                            v-model="form.variants[index].sales_price" placeholder="0"
                                                            input-class="form-control form-control-sm py-1 text-end"
                                                            @validate="validateField(`variants[${index}].sales_price`)"
                                                            :error="errors[`variants[${index}].sales_price`]" />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="form.variants.length">
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <VInput id="sku" v-model="form.variants[0].sku" label="SKU"
                                        @validate="validateField(`variants[0].sku`)"
                                        :error="errors[`variants[0].sku`]" />
                                </div>
                                <div class="col-md-4">
                                    <VInput input-type="number" id="purchase_price"
                                        v-model="form.variants[0].purchase_price" label="Purchase Price (Default)"
                                        @validate="validateField(`variants[0].purchase_price`)"
                                        :error="errors[`variants[0].purchase_price`]" />
                                </div>
                                <div class="col-md-4">
                                    <VInput input-type="number" id="sales_price" v-model="form.variants[0].sales_price"
                                        label="Selling Price" @validate="validateField(`variants[0].sales_price`)"
                                        :error="errors[`variants[0].sales_price`]" />
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <p class="text-muted small mb-0">
                    <i class="ti ti-info-circle me-1" />
                    Required fields are validated before save.
                </p>
                <div class="d-flex gap-2 ms-auto">
                    <button @click="cancel" class="btn btn-outline-secondary" type="button">
                        Cancel
                    </button>
                    <VButton :loading="isSubmitting" :btn-label="isEdit ? 'Update' : 'Save'" />
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { array, mixed, object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { useProductStore } from '@/stores/admin/inventory/product.js';
import { useUnitStore } from '@/stores/admin/inventory/unit.js';
import { useProductCategoryStore } from '@/stores/admin/inventory/product-category.js';
import { useBrandStore } from '@/stores/admin/inventory/brand.js';
import { storeToRefs } from 'pinia';
import { useAttributeStore } from '@/stores/admin/inventory/attribute.js';
import { useSettingStore } from '@/stores/admin/setting.js';

const props = defineProps({
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['create', 'edit'].includes(v),
    },
    productId: {
        type: [String, Number],
        default: null,
    },
});

const isEdit = computed(() => props.mode === 'edit');

const router = useRouter();
const productStore = useProductStore();

const unitStore = useUnitStore();
const categoryStore = useProductCategoryStore();
const brandStore = useBrandStore();
const attributeStore = useAttributeStore();
const settingStore = useSettingStore();

const { units } = storeToRefs(unitStore);
const { productCategories } = storeToRefs(categoryStore);
const { brands } = storeToRefs(brandStore);
const { attributes } = storeToRefs(attributeStore);
const { product } = storeToRefs(productStore);
const { setting } = storeToRefs(settingStore);

const inventoryCostingMethodName = computed(() => {
    const raw = setting.value?.data?.inventory_costing_method;
    const opts = setting.value?.data?.inventory_costing_method_options;
    if (Array.isArray(opts) && raw) {
        const hit = opts.find((o) => o.value === raw);
        if (hit?.label) {
            return hit.label;
        }
    }
    if (raw === 'weighted_average') {
        return 'Weighted average';
    }
    return 'FIFO (first in, first out)';
});

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
    has_variants: false,
    variants: [],
    attribute_values: [],
    track_stock: 0,
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);
const ready = ref(props.mode === 'create');
const isHydrating = ref(false);
const hydratedForProductId = ref(null);

const itemBarcode = ref('');

const isPhysicalProduct = computed(() => form.product_type === 'product');
const isVariableProduct = computed(() => isPhysicalProduct.value && form.has_variants === true);
const hasAttributesConfigured = computed(() => Array.isArray(attributes.value?.data) && attributes.value.data.length > 0);

function normalizeProductType(raw) {
    if (raw == null) {
        return 'product';
    }
    if (typeof raw === 'string') {
        return raw;
    }
    if (typeof raw === 'object' && raw !== null && 'value' in raw) {
        return raw.value;
    }
    return String(raw);
}

function buildVariantComboKey(attributeValueIds) {
    if (!Array.isArray(attributeValueIds) || attributeValueIds.length === 0) {
        return '_simple_';
    }
    return [...attributeValueIds].map(Number).sort((a, b) => a - b).join('-');
}

function mergeHydratedVariantIds(idMap) {
    form.variants.forEach((row) => {
        const k = buildVariantComboKey(row.attribute_values);
        const hit = idMap.get(k);
        if (hit) {
            row.id = hit.id;
            if (hit.sku != null) {
                row.sku = hit.sku;
            }
            row.sales_price = hit.sales_price != null ? String(hit.sales_price) : '';
            row.purchase_price = hit.purchase_price != null ? String(hit.purchase_price) : '';
            row.is_default = !!hit.is_default;
        }
    });
}

async function hydrateFromProduct(data) {
    isHydrating.value = true;
    try {
        const variantRows = Array.isArray(data.variants) ? data.variants : [];
        const pt = normalizeProductType(data.product_type);

        Object.assign(form, {
            product_category_id: data.product_category_id ?? '',
            product_type: pt,
            name: data.name ?? '',
            code: data.code ?? '',
            image: data.image ?? '',
            unit_id: data.unit_id ?? '',
            brand_id: data.brand_id ?? '',
            reorder_quantity: data.reorder_quantity != null ? String(data.reorder_quantity) : '',
            description: data.description ?? '',
            has_variants: !!data.has_variants,
            attribute_values: [],
            track_stock: 0,
        });
        form.variants = [];
        selectedVariants.value = [];
        variantColumnAttributes.value = [];

        const idMap = new Map();
        for (const v of variantRows) {
            const opts = v.variant_options || [];
            const avIds = opts.map((o) => o.id);
            idMap.set(buildVariantComboKey(avIds), {
                id: v.id,
                sku: v.sku,
                sales_price: v.sales_price,
                purchase_price: v.purchase_price,
                is_default: v.is_default,
            });
        }

        if (data.has_variants && pt === 'product' && variantRows.length) {
            const first = variantRows[0];
            const attrOrder = [];
            const seenAttr = new Set();
            for (const opt of first.variant_options || []) {
                const aid = opt.attribute_id;
                if (aid != null && !seenAttr.has(aid)) {
                    seenAttr.add(aid);
                    attrOrder.push(aid);
                }
            }
            const selected = [];
            for (const aid of attrOrder) {
                const valueSet = new Set();
                for (const vr of variantRows) {
                    for (const opt of vr.variant_options || []) {
                        if (opt.attribute_id === aid) {
                            valueSet.add(Number(opt.id));
                        }
                    }
                }
                selected.push({
                    attribute_id: aid,
                    values: [...valueSet],
                });
            }
            selectedVariants.value = selected;
            await nextTick();
            mergeHydratedVariantIds(idMap);
        } else {
            const src = variantRows[0] ?? data.defaultVariant;
            if (src) {
                form.variants = [{
                    id: src.id,
                    sku: src.sku ?? '',
                    sales_price: src.sales_price != null ? String(src.sales_price) : '',
                    purchase_price: src.purchase_price != null ? String(src.purchase_price) : '',
                    is_default: src.is_default ?? true,
                    value_labels: [],
                    attribute_values: [],
                }];
            } else {
                addVariants();
            }
        }
    } finally {
        isHydrating.value = false;
    }
}

const generateItemBarcode = () => {
    const segment = Math.random().toString(36).substring(2, 12).toUpperCase();
    itemBarcode.value = `BC-${segment}`;
};

const addVariants = () => {
    form.variants.push({
        sku: '',
        sales_price: '',
        purchase_price: '',
        is_default: false,
    });
};

const selectedVariants = ref([]);

const addVariantOption = () => {
    selectedVariants.value.push({
        attribute_id: '',
        values: []
    });
};

const removeVariantOption = (index) => {
    selectedVariants.value.splice(index, 1);
};

const selectedAttributeOptions = ref([]);

const selectableAttributes = (attrId = null) => {
    let attrIds = selectedVariants.value.filter(v => v.attribute_id).map(v => parseInt(v.attribute_id));
    if (attrId) {
        return attributes.value.data.filter(a =>
            !attrIds.includes(a.id) || a.id === parseInt(attrId)
        );
    }
    if (attrIds.length) {
        return attributes.value.data.filter(a => !attrIds.includes(a.id));
    }
    return attributes.value.data;
};

const attributeValues = (attrId = null, valId = null) => {
    if (!attrId) {
        return [];
    }
    const attr = attributes.value.data?.find(a => a.id == attrId);
    if (!attr?.values) {
        return [];
    }
    if (valId !== null && valId !== undefined) {
        return attr.values.find(v => v.id == valId);
    }
    return attr.values;
};

const variantColumnAttributes = ref([]);

function setVariantOptionAttribute(index, newVal) {
    const row = selectedVariants.value[index];
    if (!row) {
        return;
    }
    const prev = row.attribute_id;
    row.attribute_id = newVal;
    if (String(prev ?? '') !== String(newVal ?? '')) {
        row.values = [];
    }
}

function isVariantValueSelected(optionIndex, valueId) {
    const vals = selectedVariants.value[optionIndex]?.values ?? [];
    return vals.some((v) => Number(v) === Number(valueId));
}

function toggleVariantValue(optionIndex, valueId, checked) {
    const row = selectedVariants.value[optionIndex];
    if (!row) {
        return;
    }
    const id = Number(valueId);
    if (checked) {
        if (!row.values.some((v) => Number(v) === id)) {
            row.values.push(id);
        }
    } else {
        row.values = row.values.filter((v) => Number(v) !== id);
    }
}

function selectAllVariantValues(optionIndex) {
    const row = selectedVariants.value[optionIndex];
    if (!row?.attribute_id) {
        return;
    }
    const opts = attributeValues(row.attribute_id);
    row.values = opts.map((o) => Number(o.id));
}

function clearVariantValues(optionIndex) {
    const row = selectedVariants.value[optionIndex];
    if (row) {
        row.values = [];
    }
}

const cartesianProduct = (arr) => {
    return arr.reduce((acc, curr) => {
        return acc.flatMap(a => curr.map(b => [...a, b]));
    }, [[]]);
};

watch(() => selectedVariants.value, () => {
    if (!isVariableProduct.value) {
        return;
    }
    const variantValueGroups = selectedVariants.value
        .filter(v => v.attribute_id && v.values.length)
        .map(v =>
            v.values.map(val => ({
                attr_id: parseInt(v.attribute_id),
                value_id: parseInt(val)
            }))
        );

    if (!variantValueGroups.length) {
        variantColumnAttributes.value = [];
        form.variants = [];
        return;
    }

    const combinations = cartesianProduct(variantValueGroups);
    let cList = [];

    let attrList = [];

    combinations.forEach((cmb, index) => {
        cmb.forEach(l => {
            let check = attrList.find(a => a.attr_id === l.attr_id);
            if (!check) {
                const attr = attributes.value.data.find(d => d.id === l.attr_id);
                if (attr) {
                    attrList.push({
                        attr_id: attr.id,
                        attr_name: attr.name
                    });
                }
            }
        });
        cList.push({
            value_labels: cmb.map(attr => attributeValues(attr.attr_id, attr.value_id)?.value || ''),
            sku: '',
            sales_price: '',
            purchase_price: '',
            is_default: index === 0,
            attribute_values: cmb.map(a => a.value_id)
        });
    });

    variantColumnAttributes.value = attrList;
    form.variants = cList;
}, { deep: true });

watch(() => selectedAttributeOptions.value, (attrOptions) => {
    let values = [];
    attrOptions.forEach(attr => {
        attr.values.forEach(v => {
            if (!values.includes(v)) {
                values.push(v);
            }
        });
    });
    form.attribute_values = values;
}, { deep: true });

function resetToSimplePricing() {
    selectedVariants.value = [];
    variantColumnAttributes.value = [];
    form.variants.splice(0);
    addVariants();
}

watch(() => form.has_variants, (next) => {
    if (isHydrating.value) {
        return;
    }
    if (next === true) {
        form.variants.splice(0);
        return;
    }
    resetToSimplePricing();
});

watch(
    () => form.product_type,
    (type) => {
        if (isHydrating.value) {
            return;
        }
        if (type === 'service') {
            form.has_variants = false;
            resetToSimplePricing();
        }
    }
);

const setPricingModel = (variable) => {
    if (!isPhysicalProduct.value) {
        return;
    }
    form.has_variants = variable;
    if (variable && hasAttributesConfigured.value && selectedVariants.value.length === 0) {
        addVariantOption();
    }
};

const onProductTypeChange = () => {
    validateField('product_type');
    if (form.product_type === 'service') {
        validateField('reorder_quantity');
    }
};

const setProductType = (typeId) => {
    form.product_type = typeId;
    onProductTypeChange();
};

const applySkuPrefixFromCode = () => {
    const base = String(form.code || '').trim();
    if (!base || !form.variants.length) {
        return;
    }
    form.variants.forEach((row, i) => {
        row.sku = `${base}-${i + 1}`;
    });
    form.variants.forEach((_, i) => validateField(`variants[${i}].sku`));
};

const applyFirstPurchaseToAll = () => {
    if (form.variants.length < 2) {
        return;
    }
    const first = form.variants[0]?.purchase_price;
    form.variants.forEach((row) => {
        row.purchase_price = first;
    });
    form.variants.forEach((_, i) => validateField(`variants[${i}].purchase_price`));
};

const validations = object({
    product_type: string().required('Product type is required.'),
    product_category_id: string().required('Category is required.'),
    name: string().required('Name is required.'),
    code: string().required('Product code is required.'),
    unit_id: string().required('Unit is required.'),
    brand_id: string().nullable(),
    reorder_quantity: string().when('product_type', {
        is: 'service',
        then: (s) => s.nullable(),
        otherwise: (s) => s.required('Reorder quantity is required.'),
    }),
    description: string().nullable(),
    variants: array()
        .min(1, 'Add pricing for at least one variant.')
        .of(
            object({
                id: mixed().nullable(),
                sku: string().nullable(),
                sales_price: string().required('Selling price is required.'),
                purchase_price: string().required('Purchase price is required.'),
                attribute_values: array().nullable(),
            })
        )
        .test('variable-rules', 'Complete variant options and SKUs.', function (variants) {
            const parent = this.parent;
            const isVar = parent.product_type === 'product' && parent.has_variants === true;
            if (!isVar) {
                return true;
            }
            if (!variants?.length) {
                return this.createError({ message: 'Add option rows with values so variants can be generated.' });
            }
            for (let i = 0; i < variants.length; i++) {
                const row = variants[i];
                const attrs = row.attribute_values;
                if (!Array.isArray(attrs) || attrs.length === 0) {
                    return this.createError({ message: 'Each generated row must include option values.' });
                }
                if (!String(row.sku ?? '').trim()) {
                    return this.createError({ message: 'SKU is required for each variant row.' });
                }
            }
            return true;
        }),
});

const { errors, validateField, validateForm } = useYup(form, validations);

function buildPayload() {
    const variants = form.variants.map((v) => {
        const row = {
            sku: v.sku,
            sales_price: v.sales_price,
            purchase_price: v.purchase_price,
            is_default: v.is_default,
            attribute_values: Array.isArray(v.attribute_values) ? v.attribute_values : [],
        };
        if (v.id != null && v.id !== '') {
            row.id = v.id;
        }
        return row;
    });
    return {
        ...form,
        variants,
        has_variants: form.product_type === 'product' ? !!form.has_variants : false,
    };
}

async function tryHydrateEdit() {
    if (!isEdit.value || !props.productId) {
        return;
    }
    if (product.value.loading) {
        ready.value = false;
        return;
    }
    const data = product.value.data;
    if (!data?.id || String(data.id) !== String(props.productId)) {
        ready.value = false;
        return;
    }
    if (hydratedForProductId.value === String(props.productId)) {
        ready.value = true;
        return;
    }
    await hydrateFromProduct(data);
    hydratedForProductId.value = String(props.productId);
    ready.value = true;
}

watch(
    () => [props.productId, product.value.loading, product.value.data],
    () => tryHydrateEdit(),
    { deep: true }
);

watch(
    () => props.productId,
    async (id) => {
        if (!isEdit.value || !id) {
            return;
        }
        hydratedForProductId.value = null;
        ready.value = false;
        await productStore.getProduct(id);
    }
);

onMounted(async () => {
    unitStore.getUnits();
    categoryStore.getProductCategories();
    brandStore.getBrands();
    attributeStore.getAttributes();
    await settingStore.getSetting();
    if (isEdit.value && props.productId) {
        ready.value = false;
        await productStore.getProduct(props.productId);
        await tryHydrateEdit();
    } else {
        ready.value = true;
        addVariants();
    }
});

const generateProductCode = () => {
    const segment = Math.random().toString(36).substring(2, 10).toUpperCase();
    form.code = `PC-${segment}`;
    validateField('code');
};

const goToList = () => {
    router.push({ name: 'admin.product-list' });
};

const cancel = () => {
    resetForm();
    goToList();
};

const submitProduct = async () => {
    const validated = await validateForm();
    if (!validated) {
        return;
    }
    isSubmitting.value = true;
    try {
        const payload = buildPayload();
        if (isEdit.value) {
            const id = props.productId;
            if (!id) {
                return;
            }
            const res = await productStore.updateProduct(id, payload);
            toast(res.status, res.data.message);
            goToList();
        } else {
            const res = await productStore.storeProduct(payload);
            toast(res.status, res.data.message);
            goToList();
        }
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

function resetForm() {
    Object.assign(form, { ...initialState });
    form.variants.splice(0);
    selectedVariants.value = [];
    variantColumnAttributes.value = [];
    errors.value = {};
    itemBarcode.value = '';
    hydratedForProductId.value = null;
    if (!isEdit.value) {
        addVariants();
    }
}

</script>

<style scoped>
.product-pricing-card {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.product-pricing-card:hover {
    border-color: var(--bs-primary) !important;
}

.variant-combos-scroll {
    max-height: min(420px, 70vh);
    overflow: auto;
}

/* Tighter panel than global .modal-body-table (see _stocks.scss) — this screen only */
.variant-skus-wrap.modal-body-table {
    padding: 12px 14px;
    margin-bottom: 12px;
}

.variant-table-col-index {
    width: 3rem;
}
</style>
