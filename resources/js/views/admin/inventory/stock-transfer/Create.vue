<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        modal-class="large-modal"
        title="Add Stock Transfer">
        <template #modal-body>
            <form @submit.prevent="storeTransferWithStatus('draft')" class="row g-3">
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
                        id="from_warehouse_id"
                        v-model="form.from_warehouse_id"
                        :options="warehouses.data"
                        label="From Warehouse"
                        @validate="validateField('from_warehouse_id')"
                        :error="errors.from_warehouse_id"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="to_warehouse_id"
                        v-model="form.to_warehouse_id"
                        :options="toWarehouses"
                        label="To Warehouse"
                        @validate="validateField('to_warehouse_id')"
                        :error="errors.to_warehouse_id"
                    />
                </div>

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th style="width: 50px;">SN</th>
                                <th>Product Variant</th>
                                <th style="width: 160px;">Unit</th>
                                <th style="width: 160px;">From bin</th>
                                <th style="width: 160px;">To bin</th>
                                <th style="width: 120px;">Quantity</th>
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
                                        v-model="form.items[index].from_bin_id"
                                        :options="binsFrom"
                                        :disabled="!form.from_warehouse_id"
                                        placeholder="From bin"
                                        @validate="validateField(`items[${index}].from_bin_id`)"
                                        :error="errors[`items[${index}].from_bin_id`]"
                                    />
                                </td>
                                <td>
                                    <VSelect
                                        v-model="form.items[index].to_bin_id"
                                        :options="binsTo"
                                        :disabled="!form.to_warehouse_id"
                                        placeholder="To bin"
                                        @validate="validateField(`items[${index}].to_bin_id`)"
                                        :error="errors[`items[${index}].to_bin_id`]"
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

                <div class="col-12">
                    <VTextarea
                        id="remarks"
                        v-model="form.remarks"
                        label="Remarks"
                        @validate="validateField('remarks')"
                        :error="errors.remarks"
                    />
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
import {computed, onMounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {useStockTransferStore} from '@/stores/admin/inventory/stock-transfer.js';
import {useDateHelper} from "@/composables/dateHelper.js";
import {apiAdmin} from '@/helpers/api.js';

const DEFAULT_BIN_CODE = '__DEFAULT__';

function defaultBinIdFromList(bins) {
    if (!Array.isArray(bins) || !bins.length) {
        return '';
    }
    const d = bins.find((b) => b.code === DEFAULT_BIN_CODE);
    return String((d ?? bins[0]).id);
}

async function loadBinsForWarehouse(warehouseId) {
    if (!warehouseId) {
        return [];
    }
    const {data} = await apiAdmin(`bin?warehouse_id=${warehouseId}`);
    return data.data ?? [];
}

const stockTransferStore = useStockTransferStore();
const warehouseStore = useWarehouseStore();
const unitStore = useUnitStore();
const productStore = useProductStore();

const {currentAdDate} = useDateHelper();

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
    date: currentAdDate,
    from_warehouse_id: '',
    to_warehouse_id: '',
    remarks: '',
    status: 'draft',
    items: [
        {
            product_variant_id: '',
            unit_id: '',
            from_bin_id: '',
            to_bin_id: '',
            quantity: '',
        }
    ],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);
const binsFrom = ref([]);
const binsTo = ref([]);

function applyFromBinDefaults() {
    const id = defaultBinIdFromList(binsFrom.value);
    form.items.forEach((row) => {
        row.from_bin_id = id;
    });
}

function applyToBinDefaults() {
    const id = defaultBinIdFromList(binsTo.value);
    form.items.forEach((row) => {
        row.to_bin_id = id;
    });
}

watch(
    () => form.from_warehouse_id,
    async (v) => {
        binsFrom.value = v ? await loadBinsForWarehouse(v) : [];
        if (v) {
            applyFromBinDefaults();
        } else {
            form.items.forEach((row) => {
                row.from_bin_id = '';
            });
        }
    }
);

watch(
    () => form.to_warehouse_id,
    async (v) => {
        binsTo.value = v ? await loadBinsForWarehouse(v) : [];
        if (v) {
            applyToBinDefaults();
        } else {
            form.items.forEach((row) => {
                row.to_bin_id = '';
            });
        }
    }
);

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
        from_bin_id: defaultBinIdFromList(binsFrom.value),
        to_bin_id: defaultBinIdFromList(binsTo.value),
        quantity: '',
    });
};

const removeItem = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    from_warehouse_id: string().required('From warehouse is required.'),
    to_warehouse_id: string().required('To warehouse is required.'),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            quantity: string().required('Quantity is required.'),
            unit_id: string().nullable(),
            from_bin_id: string().required('From bin is required.'),
            to_bin_id: string().required('To bin is required.'),
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
