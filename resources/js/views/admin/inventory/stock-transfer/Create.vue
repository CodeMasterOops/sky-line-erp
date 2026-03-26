<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        modal-class="large-modal"
        title="Add Stock Transfer">
        <template #modal-body>
            <form @submit.prevent="storeTransferWithStatus('draft')" class="row g-3">
                <div class="col-md-12">
                    <VInput
                        id="reference_no"
                        v-model="form.reference_no"
                        label="Reference No"
                        @validate="validateField('reference_no')"
                        :error="errors.reference_no"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="date"
                        input-type="date"
                        v-model="form.date"
                        label="Date"
                        @validate="validateField('date')"
                        :error="errors.date"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="from_warehouse_id"
                        v-model="form.from_warehouse_id"
                        :options="warehouses.data"
                        label="From Warehouse"
                        @validate="validateField('from_warehouse_id')"
                        :error="errors.from_warehouse_id"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="to_warehouse_id"
                        v-model="form.to_warehouse_id"
                        :options="toWarehouses"
                        label="To Warehouse"
                        @validate="validateField('to_warehouse_id')"
                        :error="errors.to_warehouse_id"
                    />
                </div>
                <div class="col-md-6">
                    <VTextarea
                        id="remarks"
                        v-model="form.remarks"
                        label="Remarks"
                        @validate="validateField('remarks')"
                        :error="errors.remarks"
                    />
                </div>

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">SN</th>
                                    <th>Product Variant</th>
                                    <th style="width: 180px;">Unit</th>
                                    <th style="width: 140px;">Quantity</th>
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
                                        <VInput
                                            input-type="number"
                                            v-model="form.items[index].quantity"
                                            @validate="validateField(`items[${index}].quantity`)"
                                            :error="errors[`items[${index}].quantity`]"
                                        />
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

                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-primary me-1"
                        :disabled="isSubmitting"
                        @click="storeTransferWithStatus('draft')">
                        Create
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                        @click="storeTransferWithStatus('approved')">
                        Create & Approve
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {useStockTransferStore} from '@/stores/admin/inventory/stock-transfer.js';

const stockTransferStore = useStockTransferStore();
const warehouseStore = useWarehouseStore();
const unitStore = useUnitStore();
const productStore = useProductStore();

const createModalOpened = defineModel('createModalOpened');

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
    date: new Date().toISOString().slice(0, 10),
    from_warehouse_id: '',
    to_warehouse_id: '',
    remarks: '',
    status: 'draft',
    items: [
        {
            product_variant_id: '',
            unit_id: '',
            quantity: '',
        }
    ],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const toWarehouses = computed(() => {
    if (!form.from_warehouse_id) {
        return warehouses.value.data;
    }
    return warehouses.value.data.filter(w => w.id !== parseInt(form.from_warehouse_id));
});

const addItem = () => {
    form.items.push({
        product_variant_id: '',
        unit_id: '',
        quantity: '',
    });
};

const removeItem = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

const validations = object({
    date: string().required('Date is required.'),
    from_warehouse_id: string().required('From warehouse is required.'),
    to_warehouse_id: string().required('To warehouse is required.'),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            quantity: string().required('Quantity is required.'),
            unit_id: string().nullable(),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeTransferWithStatus = async (status) => {
    form.status = status;
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await stockTransferStore.storeTransfer(form);
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
    errors.value = {};
}
</script>
