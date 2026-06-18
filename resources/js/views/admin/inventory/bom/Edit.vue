<template>
    <VModal
        :show-modal="!!bomId"
        @close-click="closeModal"
        size="xl"
        :title="bomStore.bom.data?.name ? `Edit: ${bomStore.bom.data.name}` : 'Edit BOM'">
        <template #modal-body>
            <div v-if="bomStore.bom.loading" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>
            <form v-else @submit.prevent="handleSubmit" class="row g-3">
                <!-- Output product (read-only on edit) -->
                <div class="col-12">
                    <label class="form-label">Output Product</label>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-3 py-2 fs-13">
                            <i class="ti ti-package me-1"></i>{{ outputProductName }}
                        </span>
                        <small class="text-muted">Product cannot be changed after creation.</small>
                    </div>
                </div>

                <!-- Name, version, output qty, flags -->
                <div class="col-md-5">
                    <VInput
                        id="edit_bom_name"
                        v-model="form.name"
                        label="BOM Name"
                        required
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-2">
                    <VInput
                        id="edit_bom_version"
                        v-model="form.version"
                        label="Version"
                    />
                </div>
                <div class="col-md-2">
                    <VInput
                        id="edit_bom_output_qty"
                        v-model="form.output_qty"
                        input-type="number"
                        label="Output Qty"
                        required
                        @validate="validateField('output_qty')"
                        :error="errors.output_qty"
                    />
                </div>
                <div class="col-md-3 d-flex align-items-end gap-4 pb-2">
                    <div class="form-check">
                        <input
                            v-model="form.is_default"
                            type="checkbox"
                            class="form-check-input"
                            id="edit_bom_is_default"
                        />
                        <label class="form-check-label" for="edit_bom_is_default">Default BOM</label>
                    </div>
                    <div class="form-check">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="form-check-input"
                            id="edit_bom_is_active"
                        />
                        <label class="form-check-label" for="edit_bom_is_active">Active</label>
                    </div>
                </div>

                <!-- Materials -->
                <div class="col-12">
                    <label class="form-label">
                        Raw Materials / Components <span class="text-danger">*</span>
                    </label>
                    <ProductVariantSearchInput
                        label=""
                        placeholder="Search and add a material..."
                        physical-only
                        @select="onMaterialSelected"
                    />
                    <div v-if="errors.items" class="text-danger small mt-1">{{ errors.items }}</div>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Material</th>
                                    <th style="width:110px">Qty <span class="text-danger">*</span></th>
                                    <th style="width:130px">Type</th>
                                    <th style="width:110px">Wastage %</th>
                                    <th style="width:44px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!form.items.length">
                                    <td colspan="6" class="text-center text-muted py-3">
                                        Search and select a material above to add lines.
                                    </td>
                                </tr>
                                <tr v-for="(item, i) in form.items" :key="i">
                                    <td>{{ i + 1 }}</td>
                                    <td>{{ item._label }}</td>
                                    <td>
                                        <input
                                            v-model="item.quantity"
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            class="form-control form-control-sm"
                                        />
                                    </td>
                                    <td>
                                        <select v-model="item.item_type" class="form-select form-select-sm">
                                            <option value="material">Material</option>
                                            <option value="labour">Labour</option>
                                            <option value="overhead">Overhead</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            v-model="item.wastage_pct"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="form-control form-control-sm"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link text-danger p-0"
                                            @click="form.items.splice(i, 1)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top mt-2">
                    <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                        Update BOM
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { object, string, number, array } from 'yup';
import { storeToRefs } from 'pinia';
import VModal from '@/components/base/VModal.vue';
import VInput from '@/components/base/VInput.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import { useBomStore } from '@/stores/admin/inventory/bom.js';
import { useYup } from '@/helpers/yup.js';
import { toast } from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';

const bomId = defineModel('bomId');
const emit = defineEmits(['saved']);

const bomStore = useBomStore();
const { bom } = storeToRefs(bomStore);
const isSubmitting = ref(false);
const outputProductName = ref('');

const initialState = () => ({
    name: '',
    version: '1.0',
    output_qty: 1,
    is_active: true,
    is_default: false,
    remarks: '',
    items: [],
});

const form = reactive(initialState());

const validations = object({
    name: string().required('BOM name is required'),
    output_qty: number().required('Output qty is required').min(0.0001, 'Must be greater than 0'),
    items: array().min(1, 'At least one material is required'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

watch(() => bomId.value, (id) => {
    if (!id) { return; }
    bomStore.getBom(id).then(() => {
        populateForm(bom.value.data);
    });
});

function populateForm(data) {
    outputProductName.value = data.product_variant?.product?.name
        ?? `Variant #${data.product_variant_id}`;

    Object.assign(form, {
        name: data.name ?? '',
        version: data.version ?? '',
        output_qty: data.output_qty ?? 1,
        is_active: data.is_active ?? true,
        is_default: data.is_default ?? false,
        remarks: data.remarks ?? '',
        items: (data.items ?? []).map(i => ({
            product_variant_id: i.product_variant_id,
            quantity: i.quantity,
            item_type: i.item_type ?? 'material',
            wastage_pct: i.wastage_pct ?? 0,
            _label: i.product_variant?.product?.name ?? `Variant #${i.product_variant_id}`,
        })),
    });
}

function onMaterialSelected(variant) {
    form.items.push({
        product_variant_id: variant.id,
        quantity: 1,
        item_type: 'material',
        wastage_pct: 0,
        _label: variant.name,
    });
}

function closeModal() {
    bomId.value = null;
}

async function handleSubmit() {
    const valid = await validateForm();
    if (!valid) { return; }

    isSubmitting.value = true;
    try {
        const payload = {
            ...form,
            items: form.items.map(({ _label, ...item }) => item),
        };
        await bomStore.updateBom(bomId.value, payload);
        toast('BOM updated successfully');
        closeModal();
        emit('saved');
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
}
</script>
