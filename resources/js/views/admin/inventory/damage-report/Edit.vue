<template>
    <VModal
        :show-modal="!!report_id"
        @close-click="closeModal"
        size="xl"
        title="Edit Damage Report">
        <template #modal-body>
            <VLoader v-if="report.loading" loader-type="progress" />
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <form @submit.prevent="saveReport" class="row g-2">
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VDatepicker
                                id="dr_edit_date"
                                v-model="form.date"
                                label="Date"
                                required
                                :disabled="!isDraft"
                                @validate="validateField('date')"
                                :error="errors.date"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VInput
                                id="dr_edit_reference_no"
                                v-model="form.reference_no"
                                label="Reference No"
                                :disabled="!isDraft"
                                @validate="validateField('reference_no')"
                                :error="errors.reference_no"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VMultiselect
                                id="dr_edit_warehouse_id"
                                v-model="form.warehouse_id"
                                :options="warehouses.data"
                                label="Warehouse"
                                :required="isDraft"
                                :disabled="!isDraft"
                                @validate="validateField('warehouse_id')"
                                :error="errors.warehouse_id"
                            />
                        </div>
                        <div class="col-12">
                            <VInput
                                id="dr_edit_reason"
                                v-model="form.reason"
                                label="Reason"
                                :disabled="!isDraft"
                                @validate="validateField('reason')"
                                :error="errors.reason"
                            />
                        </div>

                        <div v-if="isDraft" class="col-12">
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
                                            <th style="width:5rem">Qty <VRequiredMark v-if="isDraft" /></th>
                                            <th style="width:6.5rem">Unit Cost</th>
                                            <th style="width:11rem">Batch</th>
                                            <th>Remarks</th>
                                            <th v-if="isDraft" style="width:3rem"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!form.items.length">
                                            <td :colspan="isDraft ? 7 : 6" class="text-center text-muted py-4">
                                                {{ isDraft ? 'Search and select a product to add lines.' : 'No line items.' }}
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
                                                    :disabled="!isDraft"
                                                    @validate="validateField(`items[${index}].quantity`)"
                                                    :error="errors[`items[${index}].quantity`]"
                                                />
                                            </td>
                                            <td>
                                                <VInput
                                                    input-type="number"
                                                    input-class="form-control form-control-sm"
                                                    v-model="form.items[index].unit_cost"
                                                    :disabled="!isDraft"
                                                    placeholder="0.00"
                                                />
                                            </td>
                                            <td>
                                                <BatchPickerInput
                                                    v-if="item.is_batch_tracked && isDraft && form.warehouse_id"
                                                    v-model="form.items[index].batch_id"
                                                    :product-variant-id="item.product_variant_id"
                                                    :warehouse-id="form.warehouse_id"
                                                />
                                                <span v-else-if="item.batch_no && !isDraft" class="small text-muted">{{ item.batch_no }}</span>
                                                <span v-else class="text-muted small">—</span>
                                            </td>
                                            <td>
                                                <VInput
                                                    input-class="form-control form-control-sm"
                                                    v-model="form.items[index].remarks"
                                                    :disabled="!isDraft"
                                                    placeholder="Optional"
                                                />
                                            </td>
                                            <td v-if="isDraft" class="text-center">
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
                                id="dr_edit_remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                :rows="2"
                                :disabled="!isDraft"
                                @validate="validateField('remarks')"
                                :error="errors.remarks"
                            />
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-cancel" @click="closeModal">Cancel</button>
                            <VButton v-if="isDraft" :loading="isSubmitting" />
                            <button v-else type="button" class="btn btn-secondary" disabled>Approved</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {array, number, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useDamageReportStore} from '@/stores/admin/inventory/damage-report.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';

const damageReportStore = useDamageReportStore();
const warehouseStore = useWarehouseStore();
const {warehouses} = storeToRefs(warehouseStore);
const {report} = storeToRefs(damageReportStore);

const report_id = defineModel('report_id');

const getInitialState = () => ({
    reference_no: '',
    date: '',
    warehouse_id: '',
    reason: '',
    remarks: '',
    status: 'draft',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);
const isDraft = computed(() => report.value.data?.status === 'draft');

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
    if (!isDraft.value) { return; }
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
        batch_no: null,
    });
};

const removeItem = (index) => { form.items.splice(index, 1); };

const buildPayload = () => ({
    reference_no: form.reference_no || null,
    date: form.date,
    warehouse_id: form.warehouse_id || null,
    reason: form.reason || null,
    remarks: form.remarks || null,
    items: form.items.map((item) => ({
        product_variant_id: item.product_variant_id,
        unit_id: item.unit_id || null,
        quantity: Number(item.quantity) || 1,
        unit_cost: item.unit_cost || null,
        remarks: item.remarks || null,
        batch_id: item.batch_id || null,
    })),
});

watch(
    () => report_id.value,
    async (id) => {
        if (id) {
            warehouseStore.getWarehouses();
            await damageReportStore.getReport(id);
            const d = report.value.data;
            form.reference_no = d.reference_no ?? '';
            form.date = d.date ?? '';
            form.warehouse_id = d.warehouse_id ? String(d.warehouse_id) : '';
            form.reason = d.reason ?? '';
            form.remarks = d.remarks ?? '';
            form.status = d.status ?? 'draft';
            form.items = (d.items || []).map((item) => ({
                product_variant_id: String(item.product_variant_id ?? ''),
                product_label: variantLabel(item.product_variant),
                unit_id: item.unit_id ? String(item.unit_id) : null,
                quantity: item.quantity != null ? String(item.quantity) : '1',
                unit_cost: item.unit_cost != null ? String(item.unit_cost) : '',
                remarks: item.remarks ?? '',
                is_batch_tracked: item.product_variant?.is_batch_tracked ?? false,
                batch_id: item.batch_id ?? null,
                batch_no: item.batch?.batch_no ?? null,
            }));
        }
    }
);

const saveReport = async () => {
    if (!isDraft.value) { return; }
    const valid = await validateForm(validations, form);
    if (!valid) { return; }
    isSubmitting.value = true;
    try {
        const res = await damageReportStore.updateReport(report_id.value, buildPayload());
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
    report_id.value = '';
};
</script>
