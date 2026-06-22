<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeModal"
        size="xl"
        title="Add Damage Report">
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="saveReport('draft')" class="row g-2">
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VDatepicker
                                id="dr_date"
                                v-model="form.date"
                                label="Date"
                                required
                                @validate="validateField('date')"
                                :error="errors.date"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VInput
                                id="dr_reference_no"
                                v-model="form.reference_no"
                                label="Reference No"
                                @validate="validateField('reference_no')"
                                :error="errors.reference_no"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <label class="form-label">Warehouse <VRequiredMark /></label>
                            <select
                                class="form-select"
                                v-model="form.warehouse_id"
                                @blur="validateField('warehouse_id')">
                                <option value="">— Select Warehouse —</option>
                                <option
                                    v-for="w in warehouses.data"
                                    :key="w.id"
                                    :value="w.id">
                                    {{ w.name }}
                                </option>
                            </select>
                            <div v-if="errors.warehouse_id" class="invalid-feedback d-block">{{ errors.warehouse_id }}</div>
                        </div>
                        <div class="col-12">
                            <VInput
                                id="dr_reason"
                                v-model="form.reason"
                                label="Reason"
                                placeholder="e.g. Fire, Flood, Expiry write-off..."
                                @validate="validateField('reason')"
                                :error="errors.reason"
                            />
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput
                                label="Add Product"
                                physical-only
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:2.75rem">#</th>
                                            <th>Product</th>
                                            <th style="width:5rem">Qty <VRequiredMark /></th>
                                            <th style="width:6.5rem">Unit Cost</th>
                                            <th style="width:11rem">Batch</th>
                                            <th>Remarks</th>
                                            <th style="width:3rem"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!form.items.length">
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Search and select a product to add lines.
                                            </td>
                                        </tr>
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td>{{ index + 1 }}</td>
                                            <td class="text-start">{{ item.product_label }}</td>
                                            <td>
                                                <VInput
                                                    input-type="number"
                                                    input-class="form-control form-control-sm"
                                                    v-model="form.items[index].quantity"
                                                    @validate="validateField(`items[${index}].quantity`)"
                                                    :error="errors[`items[${index}].quantity`]"
                                                />
                                            </td>
                                            <td>
                                                <VInput
                                                    input-type="number"
                                                    input-class="form-control form-control-sm"
                                                    v-model="form.items[index].unit_cost"
                                                    placeholder="0.00"
                                                />
                                            </td>
                                            <td>
                                                <BatchPickerInput
                                                    v-if="item.is_batch_tracked && form.warehouse_id"
                                                    v-model="form.items[index].batch_id"
                                                    :product-variant-id="item.product_variant_id"
                                                    :warehouse-id="form.warehouse_id"
                                                />
                                                <span v-else class="text-muted small">—</span>
                                            </td>
                                            <td>
                                                <VInput
                                                    input-class="form-control form-control-sm"
                                                    v-model="form.items[index].remarks"
                                                    placeholder="Optional"
                                                />
                                            </td>
                                            <td class="text-center">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    @click="removeItem(index)">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <VTextarea
                                id="dr_remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                :rows="2"
                                @validate="validateField('remarks')"
                                :error="errors.remarks"
                            />
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-cancel" @click="closeModal">Cancel</button>
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                :disabled="isSubmitting"
                                @click="saveReport('draft')">
                                Save as Draft
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="isSubmitting"
                                @click="saveReport('approved')">
                                Save &amp; Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {array, number, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useDateHelper} from '@/composables/dateHelper.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useDamageReportStore} from '@/stores/admin/inventory/damage-report.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';

const damageReportStore = useDamageReportStore();
const warehouseStore = useWarehouseStore();
const {warehouses} = storeToRefs(warehouseStore);
const {currentAdDate} = useDateHelper();

const createModalOpened = defineModel('createModalOpened');

watch(createModalOpened, (opened) => {
    if (opened) { warehouseStore.getWarehouses(); }
}, {flush: 'post'});

const getInitialState = () => ({
    reference_no: '',
    date: currentAdDate,
    warehouse_id: '',
    reason: '',
    remarks: '',
    status: 'draft',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

const validations = object({
    date: string().required('Date is required.'),
    warehouse_id: string().required('Warehouse is required.'),
    items: array().of(
        object({
            product_variant_id: string().required(),
            quantity: number().typeError('Quantity must be a number.').positive('Quantity must be greater than zero.').required('Quantity is required.'),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) { label += ` (${variant.sku})`; }
    return label;
}

const onVariantSelected = (variant) => {
    const existing = form.items.findIndex(
        (i) => String(i.product_variant_id) === String(variant.id)
    );
    if (existing !== -1) {
        form.items[existing].quantity = String(Number(form.items[existing].quantity || 0) + 1);
        return;
    }
    form.items.push({
        product_variant_id: variant.id,
        product_label: variantLabel(variant),
        unit_id: variant.unit_id ?? null,
        quantity: '1',
        unit_cost: variant.purchase_price ? String(variant.purchase_price) : '',
        remarks: '',
        is_batch_tracked: variant.is_batch_tracked ?? false,
        batch_id: null,
    });
};

const removeItem = (index) => { form.items.splice(index, 1); };

const buildPayload = () => ({
    reference_no: form.reference_no || null,
    date: form.date,
    warehouse_id: form.warehouse_id || null,
    reason: form.reason || null,
    remarks: form.remarks || null,
    status: form.status,
    items: form.items.map((item) => ({
        product_variant_id: item.product_variant_id,
        unit_id: item.unit_id || null,
        quantity: Number(item.quantity) || 1,
        unit_cost: item.unit_cost || null,
        remarks: item.remarks || null,
        batch_id: item.batch_id || null,
    })),
});

const saveReport = async (status) => {
    form.status = status;
    const valid = await validateForm(validations, form);
    if (!valid) { return; }
    isSubmitting.value = true;
    try {
        const res = await damageReportStore.storeReport(buildPayload());
        toast(res.status, res.data.message);
        closeModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    Object.assign(form, getInitialState());
    errors.value = {};
    createModalOpened.value = false;
};
</script>
