<template>
    <VModal
        :show-modal="!!challanId"
        @close-click="closeEditModal"
        size="xl"
        modal-class="edit-sales-modal"
        title="Update Delivery Challan">
        <template #modal-body>
            <VLoader v-if="challan.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="updateChallan" class="row g-3">
                        <div class="col-lg-6 col-md-6">
                            <VMultiselect
                                id="party_id"
                                v-model="form.party_id"
                                :options="parties.data"
                                label="Customer"
                                :disabled="!isDraft"
                                :filter-results="false"
                                required
                                @validate="validateField('party_id')"
                                @search-change="debouncedPartySearch"
                                :error="errors.party_id"
                            />
                            <PartyMetaPanel
                                v-if="resolvedParty"
                                :party="resolvedParty"
                                pan-heading="Customer PAN"
                            />
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <VDatepicker
                                id="challan_date"
                                v-model="form.challan_date"
                                label="Challan Date"
                                required
                                :disabled="!isDraft"
                                @validate="validateField('challan_date')"
                                :error="errors.challan_date"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VInput
                                id="receiver_name"
                                v-model="form.receiver_name"
                                label="Receiver Name"
                                :disabled="!isDraft"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VInput
                                id="delivery_address"
                                v-model="form.delivery_address"
                                label="Delivery Address"
                                :disabled="!isDraft"
                            />
                        </div>
                        <div class="col-12">
                            <VTextarea
                                id="remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                :disabled="!isDraft"
                            />
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                ref="productSearchRef"
                                label="Product"
                                required
                                physical-only
                                @select="onVariantSelected"
                            />
                            <small class="text-muted">
                                Warehouse is chosen when you add a product — auto-selected if only one has stock.
                            </small>
                        </div>

                        <div v-if="form.warehouse_name" class="col-12">
                            <div class="alert alert-light border py-2 mb-0">
                                <i class="ti ti-building-warehouse me-1"></i>
                                Dispatch warehouse: <strong>{{ form.warehouse_name }}</strong>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 dc-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="dc-col-sn">SN</th>
                                        <th class="dc-col-product">Product</th>
                                        <th class="dc-col-qty">Qty</th>
                                        <th class="dc-col-rate">Rate</th>
                                        <th class="text-end dc-col-total">Total</th>
                                        <th>Remarks</th>
                                        <th v-if="isDraft" class="text-center dc-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 7 : 6" class="text-center text-muted py-4">
                                            {{ isDraft ? 'Search and select a product to add lines.' : 'No line items.' }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td class="text-start">
                                            <div class="dc-line-product">
                                                <span class="dc-line-product__name">{{ item.product_label }}</span>
                                                <span v-if="item.sku" class="dc-line-product__sku">{{ item.sku }}</span>
                                                <span v-if="item.unit_name" class="dc-line-product__unit">{{ item.unit_name }}</span>
                                                <span
                                                    v-if="item.stock_qty != null"
                                                    class="dc-line-product__stock"
                                                    :class="{ 'is-over': Number(item.quantity) > item.stock_qty }">
                                                    Stock {{ item.stock_qty }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].quantity"
                                                :disabled="!isDraft"
                                                :max="item.stock_qty ?? undefined"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].rate"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td class="text-end fw-semibold">{{ formatMoney(lineTotalMoney(item)) }}</td>
                                        <td>
                                            <VInput
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].remarks"
                                                :disabled="!isDraft"
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
                                    <tfoot v-if="form.items.length" class="table-secondary fw-bold">
                                    <tr>
                                        <td :colspan="isDraft ? 4 : 4" class="text-end">Grand Total</td>
                                        <td class="text-end">{{ formatMoney(grandTotal) }}</td>
                                        <td :colspan="isDraft ? 2 : 1"></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button @click="closeEditModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <VButton v-if="isDraft" :loading="isSubmitting"/>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>

    <WarehousePickerModal
        ref="warehousePickerRef"
        modal-id="dc-edit-warehouse-picker"
        confirm-label="Dispatch from Warehouse"
        @confirm="onWarehousePicked"
        @cancel="onWarehousePickCancelled"
    />
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {computed, reactive, ref, watch} from 'vue';
import {storeToRefs} from 'pinia';
import dayjs from 'dayjs';
import {array, object, string} from 'yup';
import {toast} from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';
import {useYup} from '@/helpers/yup';
import {usePartyStore} from '@/stores/admin/party.js';
import {useDeliveryChallanStore} from '@/stores/admin/inventory/delivery-challan.js';
import {useDeliveryChallanForm} from '@/composables/useDeliveryChallanForm.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import WarehousePickerModal from '@/components/modal/WarehousePickerModal.vue';

const emit = defineEmits(['saved']);
const challanId = defineModel('challanId');

const deliveryChallanStore = useDeliveryChallanStore();
const partyStore = usePartyStore();
const {challan} = storeToRefs(deliveryChallanStore);
const {parties} = storeToRefs(partyStore);

const productSearchRef = ref(null);
const isSubmitting = ref(false);
const isDraft = computed(() => challan.value.data?.status === 'draft');

const form = reactive({
    sales_order_id: '',
    party_id: '',
    warehouse_id: '',
    warehouse_name: '',
    challan_date: '',
    receiver_name: '',
    delivery_address: '',
    remarks: '',
    items: [],
});

const {
    warehousePickerRef,
    grandTotal,
    debouncedPartySearch,
    applyPartyDefaults,
    ensureDispatchWarehouse,
    addVariantLine,
    buildItemsPayload,
    onWarehousePicked,
    onWarehousePickCancelled,
    lineTotalMoney,
    variantDisplayLabel,
} = useDeliveryChallanForm(form, {partyStore});

const resolvedParty = useResolvedParty(() => form.party_id, parties, () => challan.value.data?.party);

watch(
    () => form.party_id,
    () => applyPartyDefaults(resolvedParty.value),
);

watch(
    challanId,
    async (id) => {
        if (id) {
            partyStore.getParties({filter: {type: 'customer', limit: 50, search: ''}});
            await deliveryChallanStore.getChallan(id);
            await hydrateForm(challan.value.data);
        }
    },
    {flush: 'post'},
);

async function hydrateForm(data) {
    form.sales_order_id = data.sales_order_id ? String(data.sales_order_id) : '';
    form.party_id = data.party_id ? String(data.party_id) : '';
    form.warehouse_id = data.warehouse_id ? String(data.warehouse_id) : '';
    form.warehouse_name = data.warehouse?.name ?? '';
    form.challan_date = data.challan_date ? dayjs(data.challan_date).format('YYYY-MM-DD') : '';
    form.receiver_name = data.receiver_name || '';
    form.delivery_address = data.delivery_address || '';
    form.remarks = data.remarks || '';
    form.items = (data.challan_items || []).map((item) => ({
        product_variant_id: item.product_variant_id,
        product_label: variantDisplayLabel(item.product_variant ?? {}),
        sku: item.product_variant?.sku ?? '',
        unit_id: item.unit_id ?? item.product_variant?.unit_id ?? '',
        unit_name: item.unit?.name ?? item.product_variant?.unit?.name ?? '',
        sales_order_item_id: item.sales_order_item_id ? String(item.sales_order_item_id) : '',
        quantity: String(item.quantity),
        rate: String(item.rate),
        remarks: item.remarks || '',
        stock_qty: null,
    }));

    await ensureDispatchWarehouse();
}

const onVariantSelected = async (variant) => {
    const added = await addVariantLine(variant);

    if (added) {
        productSearchRef.value?.focus?.();
    }
};

const removeItem = (index) => {
    form.items.splice(index, 1);

    if (!form.items.length) {
        form.warehouse_id = '';
        form.warehouse_name = '';
    }
};

const buildPayload = () => ({
    sales_order_id: form.sales_order_id || null,
    party_id: form.party_id || null,
    warehouse_id: form.warehouse_id,
    challan_date: form.challan_date,
    receiver_name: form.receiver_name || null,
    delivery_address: form.delivery_address || null,
    remarks: form.remarks || null,
    items: buildItemsPayload(),
});

const validations = object({
    party_id: string().required('Customer is required.'),
    warehouse_id: string().required('Add a product to select the dispatch warehouse.'),
    challan_date: string().required('Challan date is required.'),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            quantity: string().required('Quantity is required.'),
        }),
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateChallan = async () => {
    if (form.items.length && !form.warehouse_id) {
        const warehouseReady = await ensureDispatchWarehouse();
        if (!warehouseReady) {
            return;
        }
    }

    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await deliveryChallanStore.updateChallan(challan.value.data.id, buildPayload());
        toast(res.status, res.data.message);
        emit('saved');
        closeEditModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeEditModal = () => {
    challanId.value = '';
    errors.value = {};
};

</script>

<style scoped>
.dc-lines-table :deep(.form-control) {
    min-width: 4.25rem;
}

.dc-lines-table th,
.dc-lines-table td {
    vertical-align: middle;
}

.dc-line-product__name {
    display: block;
    font-weight: 500;
}

.dc-line-product__sku,
.dc-line-product__unit {
    display: block;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
}

.dc-line-product__stock {
    display: block;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
}

.dc-line-product__stock.is-over {
    color: var(--bs-danger);
}
</style>
