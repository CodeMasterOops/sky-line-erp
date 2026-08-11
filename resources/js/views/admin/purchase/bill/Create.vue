<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        size="xl"
        modal-class="add-centered"
        title="Add Bill">
        <template #modal-body>
            <form @submit.prevent="storeBillWithStatus('draft')" class="row g-3">
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VDatepicker
                                id="bill_date"
                                v-model="form.bill_date"
                                label="Bill Date"
                                @validate="validateField('bill_date')"
                                :error="errors.bill_date"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VDatepicker
                                id="due_date"
                                v-model="form.due_date"
                                label="Due Date"
                                @validate="validateField('due_date')"
                                :error="errors.due_date"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1">
                                    <VMultiselect
                                        id="party_id"
                                        v-model="form.party_id"
                                        :options="parties.data"
                                        label="Supplier Name"
                                        :filter-results="false"
                                        @validate="validateField('party_id')"
                                        required
                                        @search-change="debouncedSupplierSearch"
                                        :error="errors.party_id"
                                    />
                                </div>
                                <div class="ps-0">
                                    <div class="add-icon">
                                        <a
                                            href="#"
                                            class="bg-dark text-white p-2 rounded d-inline-flex align-items-center justify-content-center"
                                            title="Add supplier"
                                            @click.prevent="createSupplierOpened = true">
                                            <vue-feather type="plus-circle" class="plus"></vue-feather>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <PartyMetaPanel
                                v-if="resolvedParty"
                                :party="resolvedParty"
                                pan-heading="Seller PAN"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VMultiselect
                                id="warehouse_id"
                                v-model="form.warehouse_id"
                                :options="warehouseOptionsTree"
                                label="Warehouse"
                                @validate="validateField('warehouse_id')"
                                :error="errors.warehouse_id"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VInput
                                id="supplier_invoice_no"
                                v-model="form.supplier_invoice_no"
                                label="Supplier Invoice No"
                                placeholder="Supplier invoice reference"
                            />
                        </div>
                        <div v-if="form.party_id" class="col-12">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    :disabled="isSubmitting || !form.party_id"
                                    @click="openGrnImport">
                                    <i class="ti ti-package-import me-1"></i>
                                    Load from GRN
                                </button>
                                <small v-if="hasGrnLines" class="text-muted">
                                    GRN lines loaded — additional charges were applied on the GRN.
                                </small>
                            </div>
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput purchasable-only
                                label="Product"
                                required
                                physical-only
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 order-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="po-col-sn">SN</th>
                                        <th class="po-col-product">Product</th>
                                        <th class="po-col-grn">GRN</th>
                                        <th class="po-col-qty">Qty</th>
                                        <th
                                            class="po-col-rate"
                                            title="Purchase rate; inventory cost is net of line discount and excludes tax.">
                                            Rate (purchase)
                                        </th>
                                        <th class="po-col-disc">Discount</th>
                                        <th class="po-col-tax">Tax</th>
                                        <th class="text-end po-col-total">Total</th>
                                        <th class="po-col-batch">Batch</th>
                                        <th class="text-center po-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="10" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`n-${index}-${item.product_variant_id}`"
                                        v-memo="[
                                            item.quantity,
                                            item.rate,
                                            item.line_discount_type,
                                            item.line_discount_value,
                                            item.tax_id,
                                            item.create_batch,
                                            item.batch_id,
                                            item.batch_no,
                                            item.mfg_date,
                                            item.expiry_date,
                                            form.warehouse_id,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate po-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td class="text-muted small">
                                            {{ item.grn_no || '—' }}
                                        </td>
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
                                                v-model="form.items[index].rate"
                                                @validate="validateField(`items[${index}].rate`)"
                                                :error="errors[`items[${index}].rate`]"
                                            />
                                        </td>
                                        <td class="po-discount-cell">
                                            <VDiscountAmountTypeGroup selector-mode="toggle"
                                                :input-id="`bill_line_disc_${index}`"
                                                :input-aria-label="`Line ${index + 1} discount`"
                                                v-model="form.items[index].line_discount_value"
                                                v-model:discount-type="form.items[index].line_discount_type"
                                                :error="errors[`items[${index}].line_discount_value`]"
                                                :disabled="isSubmitting"
                                                extra-group-class="po-discount-input-group"
                                                compact-toggle
                                                @blur="validateField(`items[${index}].line_discount_value`)"
                                                @update:discount-type="
                                                    () => {
                                                        validateField(`items[${index}].line_discount_type`);
                                                        validateField(`items[${index}].line_discount_value`);
                                                    }
                                                "
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                v-model="form.items[index].tax_id"
                                                select-class="form-select form-select-sm line-item-tax-select"
                                                :options="lineTaxOptions"
                                                @validate="validateField(`items[${index}].tax_id`)"
                                                :error="errors[`items[${index}].tax_id`]"
                                            />
                                        </td>
                                        <td class="text-end po-col-total">
                                            <span class="po-line-total">{{ formatMoney(calcLineTotal(item, index)) }}</span>
                                        </td>
                                        <td class="po-col-batch">
                                            <span v-if="item.grn_item_id" class="text-muted small">From GRN</span>
                                            <BatchLineInput
                                                v-else-if="item.is_batch_tracked"
                                                :line="form.items[index]"
                                                :product-variant-id="item.product_variant_id"
                                                :warehouse-id="form.warehouse_id"
                                            />
                                            <span v-else class="text-muted small">—</span>
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

                        <div v-if="hasGrnLines && grnLandedCosts.length" class="col-12">
                            <h6 class="mb-2">GRN Additional Charges <span class="text-muted small">(applied on GRN)</span></h6>
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Post As</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">VAT</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(cost, idx) in grnLandedCosts" :key="idx">
                                        <td>{{ cost.cost_type }}</td>
                                        <td>{{ cost.treatment === 'capitalized' ? 'Add to item cost' : 'Post as expense' }}</td>
                                        <td class="text-end">{{ formatMoney(cost.amount) }}</td>
                                        <td class="text-end">{{ formatMoney(cost.vat_amount) }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="!hasGrnLines" class="col-12">
                            <div class="form-check mb-2">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="show_landed_costs_create"
                                    v-model="showLandedCosts"
                                    @change="!showLandedCosts && (form.landed_costs = [])"
                                />
                                <label class="form-check-label" for="show_landed_costs_create">
                                    Add Additional Charges (freight, customs, etc.)
                                </label>
                            </div>
                        </div>

                        <div v-if="!hasGrnLines && showLandedCosts" class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0">Additional Charges</h6>
                                    <small class="text-muted">
                                        "Add to item cost" increases inventory cost. "Post as expense" posts separately.
                                    </small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="addLandedCost">
                                    <i class="ti ti-plus me-1"></i> Add Charge
                                </button>
                            </div>
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 landed-costs-table">
                                    <thead>
                                    <tr>
                                        <th class="landed-col-type">Type / Note</th>
                                        <th class="landed-col-treatment">Post As</th>
                                        <th class="landed-col-allocation">Distribute By</th>
                                        <th class="text-end landed-col-amount">Amount</th>
                                        <th class="landed-col-vat">VAT</th>
                                        <th class="landed-col-account">Account</th>
                                        <th class="text-center landed-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.landed_costs.length">
                                        <td colspan="7" class="text-center text-muted py-3">No additional charges added.</td>
                                    </tr>
                                    <tr v-for="(cost, index) in form.landed_costs" :key="index">
                                        <td>
                                            <VInput input-class="form-control form-control-sm" v-model="form.landed_costs[index].cost_type" placeholder="e.g. Transport" />
                                            <VInput input-class="form-control form-control-sm mt-1" v-model="form.landed_costs[index].description" placeholder="Note (optional)" />
                                        </td>
                                        <td>
                                            <VSelect select-class="form-select form-select-sm" v-model="form.landed_costs[index].treatment" :options="treatmentOptions" />
                                        </td>
                                        <td>
                                            <VSelect
                                                v-if="cost.treatment !== 'expense'"
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].allocation_method"
                                                :options="allocationOptions"
                                            />
                                            <span v-else class="text-muted small">—</span>
                                        </td>
                                        <td>
                                            <VInput input-type="number" input-class="form-control form-control-sm text-end" v-model="form.landed_costs[index].amount" :min-value="0" />
                                        </td>
                                        <td>
                                            <VInput input-type="number" input-class="form-control form-control-sm text-end" v-model="form.landed_costs[index].vat_amount" :min-value="0" />
                                            <div v-if="Number(form.landed_costs[index].vat_amount) > 0" class="d-flex align-items-center gap-1 mt-1">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input mt-0 flex-shrink-0"
                                                    :id="`lc_claim_${index}`"
                                                    v-model="form.landed_costs[index].vat_claim"
                                                />
                                                <label :for="`lc_claim_${index}`" class="small text-muted mb-0" style="cursor:pointer">Claim input VAT</label>
                                            </div>
                                        </td>
                                        <td>
                                            <VSelect select-class="form-select form-select-sm" v-model="form.landed_costs[index].account_id" :options="accounts.data" placeholder="Account" />
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLandedCost(index)"><i class="ti ti-trash"></i></button>
                                        </td>
                                    </tr>
                                    </tbody>
                                    <tfoot v-if="form.landed_costs.length" class="table-secondary fw-bold">
                                    <tr>
                                        <td colspan="3" class="text-end">Charge Total</td>
                                        <td class="text-end">{{ formatMoney(landedCostSummary.amount) }}</td>
                                        <td class="text-end">{{ formatMoney(landedCostSummary.vat) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-6 ms-auto">
                            <div class="total-order w-100 max-widthauto m-auto mb-4">
                                <ul>
                                    <li>
                                        <h4>Sub total</h4>
                                        <h5>{{ formatMoney(summary.subtotal) }}</h5>
                                    </li>
                                    <li class="po-total-order-discount">
                                        <h4>Discount</h4>
                                        <div class="po-total-order-discount__controls">
                                            <VDiscountAmountTypeGroup selector-mode="toggle"
                                                v-model="form.order_discount_value"
                                                v-model:discount-type="form.order_discount_type"
                                                :error="errors.order_discount_value"
                                                input-id="bill_order_discount_value"
                                                input-aria-label="Order-level discount"
                                                :disabled="isSubmitting"
                                                extra-group-class="po-order-disc-input-group w-100"
                                                compact-toggle
                                                @blur="validateField('order_discount_value')"
                                                @update:discount-type="
                                                    () => {
                                                        validateField('order_discount_type');
                                                        validateField('order_discount_value');
                                                    }
                                                "
                                            />
                                        </div>
                                        <h5>{{ formatMoney(summary.totalDiscount) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Non-taxable (net)</h4>
                                        <h5>{{ formatMoney(summary.nonTaxableBase) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Taxable (net)</h4>
                                        <h5>{{ formatMoney(summary.taxableBase) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Tax</h4>
                                        <h5>{{ formatMoney(summary.tax) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Grand total</h4>
                                        <h5>{{ formatMoney(summary.grandTotal) }}</h5>
                                    </li>
                                </ul>
                            </div>
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

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button @click="closeCreateModal" class="btn btn-cancel" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                :disabled="isSubmitting"
                                @click="storeBillWithStatus('draft')">
                                Save as Draft
                            </button>
                            <button
                                type="button"
                                class="btn btn-submit add-sale btn-primary"
                                :disabled="isSubmitting"
                                @click="storeBillWithStatus('approved')">
                                Save &amp; Approve
                            </button>
                        </div>
            </form>
        </template>
    </VModal>
    <VModal
        :show-modal="grnImportOpen"
        @close-click="grnImportOpen = false"
        size="lg"
        title="Load billable GRN lines">
        <template #modal-body>
            <VLoader v-if="loadingBillableGrn" loader-type="progress"/>
            <div v-else-if="!billableGrnItems.length" class="text-muted py-3">No billable GRN lines for this supplier.</div>
            <div v-else class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                    <tr>
                        <th></th>
                        <th>GRN</th>
                        <th>Product</th>
                        <th>Remaining</th>
                        <th>Unit cost</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="row in billableGrnItems" :key="row.grn_item_id">
                        <td><input v-model="selectedGrnItemIds" type="checkbox" class="form-check-input" :value="String(row.grn_item_id)"></td>
                        <td>{{ row.grn_no }}</td>
                        <td>{{ variantLabel(row.product_variant || {}) }}</td>
                        <td>{{ row.remaining_qty }}</td>
                        <td>{{ row.unit_cost }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-cancel" @click="grnImportOpen = false">Cancel</button>
                <button type="button" class="btn btn-primary" :disabled="!selectedGrnItemIds.length" @click="importSelectedGrnLines">Import selected</button>
            </div>
        </template>
    </VModal>
    <CreateSupplier
        v-if="createSupplierOpened"
        v-model:createModalOpened="createSupplierOpened"
        type="supplier"
    />
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {reactive, ref, toRef, watch, computed} from 'vue';
import debounce from 'lodash/debounce';
import {useToast} from 'vue-toastification';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useBillStore} from '@/stores/admin/purchase/bill.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {lineDiscountMoneyFromItem, lineNetFromItem, orderDiscountMoney, buildOrderAllocations, mergePoOrderDiscountIntoLineDiscounts} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useLineItemTaxOptions, parseTaxSelection, taxSelectionValue} from '@/composables/useLineItemTaxOptions.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchLineInput from '@/components/inventory/BatchLineInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import CreateSupplier from '@/views/admin/party/Create.vue';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import {usePurchaseOrderStore} from "@/stores/admin/purchase/purchase-order.js";
import {useGrnStore} from '@/stores/admin/inventory/grn.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';

const billStore = useBillStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();
const purchaseOrderStore = usePurchaseOrderStore();
const grnStore = useGrnStore();
const accountStore = useAccountStore();

const {currentAdDate} = useDateHelper();

const createModalOpened = defineModel('createModalOpened');
const purchaseOrderId = defineModel('purchaseOrderId');
const createSupplierOpened = ref(false);
const showLandedCosts = ref(false);

const {parties} = storeToRefs(partyStore);
const {taxes, taxGroups} = storeToRefs(taxStore);
const {warehouses, stockLocationOptionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);
const {order} = storeToRefs(purchaseOrderStore);
const {accounts} = storeToRefs(accountStore);

const grnImportOpen = ref(false);
const loadingBillableGrn = ref(false);
const billableGrnItems = ref([]);
const selectedGrnItemIds = ref([]);

const treatmentOptions = [
    {id: 'capitalized', name: 'Add to item cost'},
    {id: 'expense', name: 'Post as expense'},
];

const allocationOptions = [
    {id: 'value', name: 'By Value'},
    {id: 'quantity', name: 'By Quantity'},
    {id: 'equal', name: 'Equal'},
];

const newLandedCostTemplate = () => ({
    cost_type: '',
    description: '',
    treatment: 'capitalized',
    allocation_method: 'value',
    amount: 0,
    vat_amount: 0,
    vat_claim: true,
    account_id: '',
});

const lineTaxOptions = useLineItemTaxOptions(taxes, taxGroups);

const debouncedSupplierSearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'supplier',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

watch(createModalOpened, (opened) => {
        if (opened) {
            taxStore.getTaxes();
            taxStore.getTaxGroups();
            warehouseStore.getWarehouses();
            accountStore.getAccounts();
            partyStore.getParties({
                filter: {
                    type: 'supplier',
                    limit: 50,
                    search: '',
                },
            });
            if (purchaseOrderId.value) {
                loadFromPurchaseOrder();
            }
        }
    },
    {flush: 'post'}
);

const getInitialState = () => ({
    bill_date: currentAdDate,
    due_date: '',
    party_id: '',
    purchase_order_id: '',
    warehouse_id: '',
    supplier_invoice_no: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
    landed_costs: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);

const hasGrnLines = computed(() => form.items.some((item) => item.grn_item_id));
const canEnterLandedCosts = computed(() => !hasGrnLines.value);
const grnLandedCosts = computed(() => {
    const map = new Map();
    form.items.forEach((item) => {
        (item.grn_landed_costs || []).forEach((cost) => {
            map.set(`${cost.id}-${cost.cost_type}`, cost);
        });
    });
    return [...map.values()];
});

const landedCostSummary = computed(() =>
    form.landed_costs.reduce((summary, cost) => {
        summary.amount += Number(cost.amount || 0);
        summary.vat += Number(cost.vat_amount || 0);
        return summary;
    }, {amount: 0, vat: 0})
);


const addLandedCost = () => {
    form.landed_costs.push(newLandedCostTemplate());
};

const removeLandedCost = (index) => {
    form.landed_costs.splice(index, 1);
};

const openGrnImport = async () => {
    if (!form.party_id) {
        return;
    }
    grnImportOpen.value = true;
    loadingBillableGrn.value = true;
    selectedGrnItemIds.value = [];
    try {
        billableGrnItems.value = await grnStore.getBillableItems({
            partyId: form.party_id,
            warehouseId: form.warehouse_id || null,
        });
    } finally {
        loadingBillableGrn.value = false;
    }
};

const importSelectedGrnLines = () => {
    const selected = billableGrnItems.value.filter((row) =>
        selectedGrnItemIds.value.includes(String(row.grn_item_id))
    );

    selected.forEach((row) => {
        if (form.items.some((item) => String(item.grn_item_id) === String(row.grn_item_id))) {
            return;
        }
        if (row.warehouse_id && !form.warehouse_id) {
            form.warehouse_id = String(row.warehouse_id);
        }
        if (row.supplier_invoice_no && !form.supplier_invoice_no) {
            form.supplier_invoice_no = row.supplier_invoice_no;
        }
        form.items.push({
            product_variant_id: row.product_variant_id,
            product_label: variantLabel(row.product_variant || {}),
            grn_item_id: row.grn_item_id,
            grn_no: row.grn_no,
            grn_landed_costs: row.grn_landed_costs || [],
            list_sale_snapshot: row.product_variant?.sales_price || 0,
            unit_id: row.unit_id ?? '',
            quantity: String(row.remaining_qty),
            rate: String(row.unit_cost),
            tax_id: '',
            tax_line_type: 'taxable',
            line_discount_type: 'fixed',
            line_discount_value: '0',
        });
    });

    form.landed_costs = [];
    grnImportOpen.value = false;
};

const loadFromPurchaseOrder = async () => {
    await purchaseOrderStore.getOrder(purchaseOrderId.value);
    const data = order.value.data;
    form.party_id = data.party_id || '';
    form.remarks = data.remarks || '';
    form.purchase_order_id = purchaseOrderId.value;

    const items = data.items || [];
    const hasPoLineTypes = items.some((it) => it.line_discount_type != null);

    if (hasPoLineTypes) {
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value =
            data.order_discount_value != null && data.order_discount_value !== ''
                ? String(data.order_discount_value)
                : '0';
        items.forEach((item) => {
            form.items.push({
                product_variant_id: item.product_variant_id,
                product_label: variantLabel(item.product_variant),
                list_sale_snapshot: item.product_variant?.sales_price || 0,
                unit_id: item.unit_id ?? '',
                quantity: String(item.quantity),
                rate: String(item.rate),
                tax_id: item.tax_id || '',
                tax_line_type: 'taxable',
                line_discount_type: item.line_discount_type || 'fixed',
                line_discount_value: String(item.line_discount_value ?? 0),
                is_batch_tracked: !!item.product_variant?.is_batch_tracked,
                ...batchLineDefaults(),
            });
        });
    } else {
        const mergedDiscounts = mergePoOrderDiscountIntoLineDiscounts(items, data.order_discount_amount);
        form.order_discount_type = 'fixed';
        form.order_discount_value = '0';
        items.forEach((item, i) => {
            form.items.push({
                product_variant_id: item.product_variant_id,
                product_label: variantLabel(item.product_variant),
                list_sale_snapshot: item.product_variant?.sales_price || 0,
                unit_id: item.unit_id ?? '',
                quantity: String(item.quantity),
                rate: String(item.rate),
                tax_id: item.tax_id || '',
                tax_line_type: 'taxable',
                line_discount_type: 'fixed',
                line_discount_value: String(mergedDiscounts[i] ?? item.discount_amount ?? 0),
                is_batch_tracked: !!item.product_variant?.is_batch_tracked,
                ...batchLineDefaults(),
            });
        });
    }
};

function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
}

function defaultLineRateString(variant) {
    const n = Number(variant.purchase_price ?? variant.sales_price ?? 0);
    return String(Number.isFinite(n) ? n : 0);
}

const onVariantSelected = (variant) => {
    if (variant.is_service) {
        useToast().warning('Services cannot be purchased on bills. Use expenses for service payments.');

        return;
    }

    const vid = variant.id;
    const existing = form.items.findIndex((i) => String(i.product_variant_id) === String(vid));
    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        form.items[existing].quantity = String(nextQty);
        return;
    }
    form.items.push({
        product_variant_id: vid,
        product_label: variantLabel(variant),
        list_sale_snapshot: variant.sales_price ?? 0,
        unit_id: variant.unit_id ?? '',
        quantity: '1',
        rate: defaultLineRateString(variant),
        tax_id: taxSelectionValue({tax_id: variant.tax_id, tax_group_id: variant.tax_group_id}),
        tax_line_type: 'taxable',
        line_discount_type: 'fixed',
        line_discount_value: '0',
        is_batch_tracked: !!variant.is_batch_tracked,
        ...batchLineDefaults(),
    });
};

const batchLineDefaults = () => ({
    create_batch: false,
    batch_id: null,
    batch_no: '',
    mfg_date: null,
    expiry_date: null,
});

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const hasPhysicalBillItems = () => form.items.length > 0;

const validations = object({
    bill_date: string().required('Bill date is required.'),
    due_date: string().nullable(),
    party_id: string().required('Supplier is required'),
    warehouse_id: string()
        .nullable()
        .test('warehouse-required', 'Warehouse is required when purchasing stock products.', function () {
            if (!hasPhysicalBillItems()) {
                return true;
            }
            const value = this.parent.warehouse_id;
            return value != null && String(value).trim() !== '';
        }),
    order_discount_type: string().nullable(),
    order_discount_value: string().nullable(),
    items: array()
        .of(
            object({
                product_variant_id: string().required('Product is required.'),
                quantity: string().required('Quantity is required.'),
                rate: string().required('Rate is required.'),
                unit_id: string().nullable(),
                tax_id: string().nullable(),
                line_discount_type: string().nullable(),
                line_discount_value: string().nullable(),
            })
        )
        .min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const {calcLineTax, summary, syncTaxAmounts} = useLineOrderDiscountTotals({form, taxes});

function calcLineTotal(item, index) {
    const nets = form.items.map((i) => lineNetFromItem(i));
    const sumLineNet = nets.reduce((a, b) => a + b, 0);
    const orderDisc = orderDiscountMoney(sumLineNet, form.order_discount_type, form.order_discount_value);
    const allocs = buildOrderAllocations(nets, orderDisc);
    return Math.max(0, lineNetFromItem(item) - (allocs[index] || 0)) + calcLineTax(item, index);
}

const lineQtyInt = (q) => {
    const n = parseInt(String(q ?? '0'), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
};

const buildBillPayload = () => {
    syncTaxAmounts();
    const wid = form.warehouse_id;
    return {
        bill_date: form.bill_date,
        due_date: form.due_date || null,
        party_id: form.party_id || null,
        purchase_order_id: form.purchase_order_id || null,
        supplier_invoice_no: form.supplier_invoice_no || null,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            grn_item_id: item.grn_item_id || null,
            warehouse_id: wid || null,
            unit_id: item.unit_id || null,
            quantity: lineQtyInt(item.quantity),
            rate: Number(item.rate || 0),
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            ...parseTaxSelection(item.tax_id),
            tax_amount: item.tax_amount ?? 0,
            discount_amount: String(lineDiscountMoneyFromItem(item)),
            tax_line_type: item.tax_line_type || 'taxable',
            batch_id: item.grn_item_id || item.create_batch ? null : (item.batch_id || null),
            batch_no: !item.grn_item_id && item.create_batch ? (item.batch_no || null) : null,
            mfg_date: !item.grn_item_id && item.create_batch ? (item.mfg_date || null) : null,
            expiry_date: !item.grn_item_id && item.create_batch ? (item.expiry_date || null) : null,
        })),
        landed_costs: canEnterLandedCosts.value
            ? form.landed_costs
                .filter((cost) => cost.cost_type || Number(cost.amount || 0) > 0 || Number(cost.vat_amount || 0) > 0)
                .map((cost) => {
                    const vatAmount = Number(cost.vat_amount || 0);
                    return {
                        cost_type: cost.cost_type,
                        description: cost.description || null,
                        treatment: cost.treatment || 'capitalized',
                        allocation_method: cost.treatment === 'expense' ? null : (cost.allocation_method || 'value'),
                        amount: Number(cost.amount || 0),
                        vat_amount: vatAmount,
                        vat_claimable_amount: cost.vat_claim ? vatAmount : 0,
                        account_id: cost.account_id || null,
                    };
                })
            : [],
    };
};

const storeBillWithStatus = async (status) => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await billStore.storeBill(buildBillPayload());
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
    Object.assign(form, getInitialState());
    errors.value = {};
    showLandedCosts.value = false;
}
</script>

<style scoped>
.order-lines-table :deep(.form-control),
.order-lines-table :deep(.form-select) {
    min-width: 4.25rem;
}

.order-lines-table th,
.order-lines-table td {
    vertical-align: middle;
}

.order-lines-table .po-col-product {
    min-width: 11rem;
    max-width: 16rem;
}

.order-lines-table .po-col-tax {
    min-width: 7.5rem;
}

.order-lines-table .po-col-sn {
    width: 2.5rem;
}

.order-lines-table .po-col-action {
    width: 3rem;
}

.order-lines-table .po-col-batch {
    min-width: 10rem;
}

.order-lines-table .po-discount-cell {
    min-width: 8.25rem;
}

.total-order :deep(ul li.po-total-order-discount) {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
}

.total-order :deep(ul li.po-total-order-discount h4) {
    width: 28%;
    min-width: 4.5rem;
    flex: 0 0 auto;
    border-right: 1px solid var(--bs-border-color, #dee2e6);
    margin: 0;
    padding: 0.5rem 0.5rem 0.5rem 0.625rem;
    align-self: stretch;
    display: flex;
    align-items: center;
}

.total-order :deep(ul li.po-total-order-discount .po-total-order-discount__controls) {
    flex: 1 1 40%;
    min-width: 0;
    max-width: 12rem;
    padding: 0.2rem 0.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.total-order :deep(ul li.po-total-order-discount h5) {
    width: auto;
    flex: 1 0 22%;
    min-width: 3.5rem;
    margin: 0;
    text-align: right;
    border-left: 0;
    padding: 0.5rem 0.625rem 0.5rem 0.5rem;
}

.po-order-disc-input-group {
    max-width: 15rem;
}

.landed-costs-table :deep(.form-control),
.landed-costs-table :deep(.form-select) {
    min-width: 6rem;
}

.landed-col-type {
    min-width: 11rem;
}

.landed-col-treatment,
.landed-col-allocation {
    min-width: 8rem;
}

.landed-col-amount {
    min-width: 7rem;
    width: 7rem;
}

.landed-col-vat {
    min-width: 9rem;
}

.landed-col-account {
    min-width: 10rem;
}

.landed-col-action {
    width: 3rem;
}

.order-lines-table .po-col-grn {
    min-width: 5rem;
}

.order-lines-table .po-col-total {
    min-width: 5.5rem;
}

.po-line-total {
    font-weight: 600;
    font-size: 0.875rem;
}
</style>
