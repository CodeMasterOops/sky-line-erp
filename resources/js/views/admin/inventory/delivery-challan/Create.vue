<template>
    <VModal
        :show-modal="showModal"
        @close-click="closeCreateModal"
        size="xl"
        modal-class="add-centered"
        title="Create Delivery Challan"
    >
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <div v-if="salesOrderId" class="alert alert-info py-2 mb-3">
                        Creating delivery challan from Sales Order #{{ salesOrderId }}
                    </div>
                    <form @submit.prevent="saveWithStatus('draft')" class="row g-3">
                        <div class="col-lg-6 col-md-6">
                            <VMultiselect
                                id="party_id"
                                v-model="form.party_id"
                                :options="parties.data"
                                label="Customer"
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
                                @validate="validateField('challan_date')"
                                :error="errors.challan_date"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VInput
                                id="receiver_name"
                                v-model="form.receiver_name"
                                label="Receiver Name"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VInput
                                id="delivery_address"
                                v-model="form.delivery_address"
                                label="Delivery Address"
                            />
                        </div>
                        <div class="col-12">
                            <VTextarea
                                id="remarks"
                                v-model="form.remarks"
                                label="Remarks"
                            />
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput
                                ref="productSearchRef"
                                label="Product"
                                required
                                physical-only
                                show-stock
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
                                        <th class="dc-col-qty">Qty <VRequiredMark /></th>
                                        <th class="dc-col-rate">Rate</th>
                                        <th class="text-end dc-col-total">Total</th>
                                        <th class="dc-col-batch">Batch</th>
                                        <th>Remarks</th>
                                        <th class="text-center dc-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td class="text-start inv-col-product">
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
                                                @validate="validateField(`items[${index}].rate`)"
                                                :error="errors[`items[${index}].rate`]"
                                            />
                                        </td>
                                        <td class="text-end fw-semibold">{{ formatMoney(lineTotalMoney(item)) }}</td>
                                        <td class="dc-col-batch">
                                            <BatchPickerInput
                                                v-if="item.is_batch_tracked"
                                                v-model="form.items[index].batch_id"
                                                :product-variant-id="item.product_variant_id"
                                                :warehouse-id="form.warehouse_id"
                                                :required="true"
                                            />
                                            <span v-else class="text-muted small">—</span>
                                        </td>
                                        <td>
                                            <VInput
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].remarks"
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
                                    <tfoot v-if="form.items.length" class="table-secondary fw-bold">
                                    <tr>
                                        <td colspan="4" class="text-end">Grand Total</td>
                                        <td class="text-end">{{ formatMoney(grandTotal) }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button @click="closeCreateModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-secondary me-2"
                                :disabled="isSubmitting"
                                @click="saveWithStatus('draft')">
                                Save Draft
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="isSubmitting"
                                @click="saveWithStatus('approved')">
                                Save &amp; Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>

    <WarehousePickerModal
        ref="warehousePickerRef"
        modal-id="dc-create-warehouse-picker"
        confirm-label="Dispatch from Warehouse"
        @confirm="onWarehousePicked"
        @cancel="onWarehousePickCancelled"
    />
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {computed, reactive, ref, watch} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import {storeToRefs} from 'pinia';
import {array, object, string} from 'yup';
import {toast} from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';
import {useYup} from '@/helpers/yup';
import {apiAdmin} from '@/helpers/api.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useDeliveryChallanStore} from '@/stores/admin/inventory/delivery-challan.js';
import {useDeliveryChallanForm} from '@/composables/useDeliveryChallanForm.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import WarehousePickerModal from '@/components/modal/WarehousePickerModal.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const emit = defineEmits(['saved']);
const createModalOpened = defineModel('createModalOpened');
const salesOrderId = defineModel('salesOrderId');

const route = useRoute();
const router = useRouter();
const deliveryChallanStore = useDeliveryChallanStore();
const partyStore = usePartyStore();
const {parties} = storeToRefs(partyStore);
const {currentAdDate} = useDateHelper();

const productSearchRef = ref(null);
const isSubmitting = ref(false);
const openedFromRoute = computed(() => route.name === 'admin.delivery-challan-create');
const showModal = computed(() => openedFromRoute.value || !!createModalOpened.value || !!salesOrderId.value);

const getInitialState = () => ({
    sales_order_id: '',
    party_id: '',
    warehouse_id: '',
    warehouse_name: '',
    challan_date: currentAdDate,
    receiver_name: '',
    delivery_address: '',
    remarks: '',
    status: 'draft',
    items: [],
});

const form = reactive({...getInitialState()});

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

const resolvedParty = useResolvedParty(() => form.party_id, parties);

watch(
    () => form.party_id,
    () => applyPartyDefaults(resolvedParty.value),
);

watch(
    () => showModal.value,
    async (opened) => {
        if (opened) {
            partyStore.getParties({filter: {type: 'customer', limit: 50, search: ''}});
            resetForm();
            if (salesOrderId.value) {
                await loadFromSalesOrder(salesOrderId.value);
            }
        }
    },
    {flush: 'post'},
);

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

async function loadFromSalesOrder(orderId) {
    try {
        const res = await apiAdmin(`sales-order/${orderId}/deliverable-items`, 'get');
        const payload = res.data.data;
        form.sales_order_id = String(payload.sales_order_id);
        form.party_id = payload.party_id ? String(payload.party_id) : '';
        applyPartyDefaults(resolvedParty.value);

        form.items = (payload.items || []).map((item) => {
            const variant = item.product_variant ?? {};

            return {
                product_variant_id: item.product_variant_id,
                product_label: variantDisplayLabel(variant),
                sku: variant.sku ?? '',
                unit_id: item.unit_id ?? variant.unit_id ?? '',
                unit_name: item.unit?.name ?? variant.unit?.name ?? '',
                sales_order_item_id: String(item.sales_order_item_id),
                quantity: String(item.remaining_qty),
                rate: String(item.rate ?? 0),
                remarks: '',
                stock_qty: null,
            };
        });

        await ensureDispatchWarehouse();
    } catch (e) {
        showErrors(e);
    }
}

const buildPayload = () => ({
    sales_order_id: form.sales_order_id || null,
    party_id: form.party_id || null,
    warehouse_id: form.warehouse_id,
    challan_date: form.challan_date,
    receiver_name: form.receiver_name || null,
    delivery_address: form.delivery_address || null,
    remarks: form.remarks || null,
    status: form.status,
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
            rate: string().required('Rate is required.'),
        }),
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const saveWithStatus = async (status) => {
    form.status = status;

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
        const res = await deliveryChallanStore.storeChallan(buildPayload());
        toast(res.status, res.data.message);
        emit('saved');
        await closeCreateModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

function resetForm() {
    Object.assign(form, getInitialState());
    errors.value = {};
}

const closeCreateModal = async () => {
    resetForm();
    salesOrderId.value = '';

    if (openedFromRoute.value) {
        await router.push({name: 'admin.delivery-challan-list'});
        return;
    }

    createModalOpened.value = false;
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

.dc-lines-table .dc-col-product {
    min-width: 12rem;
}

.dc-lines-table .dc-col-qty,
.dc-lines-table .dc-col-rate,
.dc-lines-table .dc-col-total {
    min-width: 5rem;
}

.dc-lines-table .dc-col-sn {
    width: 2.5rem;
}

.dc-lines-table .dc-col-action {
    width: 3rem;
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
