<template>
    <VModal
        :show-modal="!!edit_adjustment_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Stock Adjustment">
        <template #modal-body>
            <VLoader v-if="adjustment.loading" loader-type="progress"/>
            <form @submit.prevent="updateAdjustment(adjustment.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VDatepicker
                        id="date"
                        v-model="form.date"
                        label="Date"
                        @validate="validateField('date')"
                        :error="errors.date"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="reference_no"
                        v-model="form.reference_no"
                        label="Reference No"
                        @validate="validateField('reference_no')"
                        :error="errors.reference_no"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="warehouse_id"
                        v-model="form.warehouse_id"
                        :options="warehouses.data"
                        label="Warehouse"
                        @validate="validateField('warehouse_id')"
                        :error="errors.warehouse_id"
                    />
                </div>

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th style="width: 50px;">SN</th>
                                <th>Product</th>
                                <th style="width: 160px;">Unit</th>
                                <th style="width: 120px;">Type</th>
                                <th style="width: 140px;">Quantity</th>
                                <th style="width: 120px;">Unit cost</th>
                                <th style="width: 60px;">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td>
                                    <VSelect
                                        v-model="form.items[index].product_variant_id"
                                        :options="productVariants.data"
                                        @validate="validateField(`items[${index}].product_variant_id`)"
                                        :error="errors[`items[${index}].product_variant_id`]"
                                    />
                                </td>
                                <td>
                                    <VSelect
                                        v-model="form.items[index].unit_id"
                                        :options="units.data"
                                        @validate="validateField(`items[${index}].unit_id`)"
                                        :error="errors[`items[${index}].unit_id`]"
                                    />
                                </td>
                                <td>
                                    <VSelect
                                        v-model="form.items[index].direction"
                                        :options="directionOptions"
                                        @validate="validateField(`items[${index}].direction`)"
                                        :error="errors[`items[${index}].direction`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.items[index].quantity"
                                        @validate="validateField(`items[${index}].quantity`)"
                                        :error="errors[`items[${index}].quantity`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        v-if="form.items[index].direction === 'in'"
                                        input-type="number"
                                        v-model="form.items[index].unit_cost"
                                        @validate="validateField(`items[${index}].unit_cost`)"
                                        :error="errors[`items[${index}].unit_cost`]"
                                    />
                                    <span v-else class="text-muted small">—</span>
                                </td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="removeItem(index)"
                                        :disabled="form.items.length === 1">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="addItem">
                        Add Item
                    </button>
                </div>

                <div class="col-md-12">
                    <VTextarea
                        id="remarks"
                        v-model="form.remarks"
                        label="Remarks"
                        @validate="validateField('remarks')"
                        :error="errors.remarks"
                    />
                </div>

                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <VButton v-if="isDraft" :loading="isSubmitting"/>
                    <button v-else type="button" class="btn btn-secondary" disabled>
                        Approved
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, onMounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {useStockAdjustmentStore} from '@/stores/admin/inventory/stock-adjustment.js';

const stockAdjustmentStore = useStockAdjustmentStore();
const warehouseStore = useWarehouseStore();
const unitStore = useUnitStore();
const productStore = useProductStore();

const edit_adjustment_id = defineModel('adjustment_id');

const {adjustment} = storeToRefs(stockAdjustmentStore);
const {warehouses} = storeToRefs(warehouseStore);
const {units} = storeToRefs(unitStore);
const {productVariants} = storeToRefs(productStore);

onMounted(() => {
    warehouseStore.getWarehouses();
    unitStore.getUnits();
    productStore.getProductVariants();
});

const initialState = {
    reference_no: '',
    date: '',
    warehouse_id: '',
    remarks: '',
    status: 'draft',
    items: [
        {
            product_variant_id: '',
            unit_id: '',
            direction: 'in',
            quantity: '',
            unit_cost: '',
        }
    ],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const directionOptions = [
    {id: 'in', name: 'In'},
    {id: 'out', name: 'Out'},
];

const addItem = () => {
    form.items.push({
        product_variant_id: '',
        unit_id: '',
        direction: 'in',
        quantity: '',
        unit_cost: '',
    });
};

const removeItem = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

watch(() => edit_adjustment_id.value, async (id) => {
    if (id) {
        await stockAdjustmentStore.getAdjustment(id);
        Object.keys(form).forEach(key => {
            if (key === 'items') {
                form.items = (adjustment.value.data.items || []).map(item => ({
                    product_variant_id: item.product_variant_id || '',
                    unit_id: item.unit_id || '',
                    direction: item.direction || 'in',
                    quantity: item.quantity || '',
                    unit_cost: item.unit_cost ?? '',
                }));
            } else {
                form[key] = adjustment.value.data[key] || '';
            }
        });
    }
});

const isDraft = computed(() => adjustment.value.data.status === 'draft');

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    warehouse_id: string().required('Warehouse is required.'),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            direction: string().required('Type is required.'),
            quantity: string().required('Quantity is required.'),
            unit_id: string().nullable(),
            unit_cost: string().when('direction', {
                is: 'in',
                then: (schema) => schema.required('Unit cost is required for adjustment in.'),
                otherwise: (schema) => schema.nullable(),
            }),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateAdjustment = async (id) => {
    if (!isDraft.value) return;
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await stockAdjustmentStore.updateAdjustment(id, form);
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
    edit_adjustment_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}
</script>
