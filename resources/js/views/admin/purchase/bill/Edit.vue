<template>
    <VModal
        :show-modal="!!edit_bill_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal"
        size="xl"
        title="Update Bill">
        <template #modal-body>
            <VLoader v-if="bill.loading" loader-type="progress"/>
            <form v-else @submit.prevent="updateBill(bill.data.id)" class="row g-3">
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VDatepicker
                                id="bill_date"
                                input-type="date"
                                v-model="form.bill_date"
                                label="Bill Date"
                                :disabled="!isDraft"
                                @validate="validateField('bill_date')"
                                :error="errors.bill_date"
                            />
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <VDatepicker
                                id="due_date"
                                input-type="date"
                                v-model="form.due_date"
                                label="Due Date"
                                :disabled="!isDraft"
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
                                        :disabled="!isDraft"
                                        @validate="validateField('party_id')"
                                        required
                                        @search-change="debouncedSupplierSearch"
                                        :error="errors.party_id"
                                    />
                                </div>
                                <div v-if="isDraft" class="ps-0">
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
                                :disabled="!isDraft"
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
                                :disabled="!isDraft"
                            />
                        </div>

                        <div v-if="isDraft && form.party_id" class="col-12">
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

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput purchasable-only
                                label="Product name / code / SKU"
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
                                            Rate (purchase)</th>
                                        <th class="po-col-disc">Discount</th>
                                        <th class="po-col-tax">Tax</th>
                                        <th class="text-end po-col-total">Total</th>
                                        <th class="po-col-batch">Batch</th>
                                        <th v-if="isDraft" class="text-center po-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 10 : 9" class="text-center text-muted py-4">
                                            No line items.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="(item.id ?? `n-${index}-${item.product_variant_id}`)"
                                        v-memo="[
                                            item.id,
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
                                            isDraft,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate po-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td class="text-muted small">{{ item.grn_no || '—' }}</td>
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
                                                v-model="form.items[index].rate"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].rate`)"
                                                :error="errors[`items[${index}].rate`]"
                                            />
                                        </td>
                                        <td class="text-end" :class="{'po-discount-cell': isDraft}">
                                            <template v-if="isDraft">
                                                <VDiscountAmountTypeGroup selector-mode="toggle"
                                                    :input-id="`bill_edit_line_disc_${index}`"
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
                                            </template>
                                            <span v-else>{{ formatMoney(lineDiscountMoneyFromItem(item)) }}</span>
                                        </td>
                                        <td>
                                            <VSelect
                                                v-model="form.items[index].tax_id"
                                                select-class="form-select form-select-sm line-item-tax-select"
                                                :options="lineTaxOptions"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].tax_id`)"
                                                :error="errors[`items[${index}].tax_id`]"
                                            />
                                        </td>
                                        <td class="text-end po-col-total">
                                            <span class="po-line-total">{{ formatMoney(calcLineTotal(item, index)) }}</span>
                                        </td>
                                        <td class="po-col-batch">
                                            <span v-if="item.grn_item_id" class="text-muted small">From GRN</span>
                                            <span v-else-if="!isDraft" class="text-muted small">{{ item.batch_no || item.batch?.batch_no || '—' }}</span>
                                            <BatchLineInput
                                                v-else-if="item.is_batch_tracked"
                                                :line="form.items[index]"
                                                :product-variant-id="item.product_variant_id"
                                                :warehouse-id="form.warehouse_id"
                                            />
                                            <span v-else class="text-muted small">—</span>
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
                                    id="show_landed_costs_edit"
                                    v-model="showLandedCosts"
                                    @change="!showLandedCosts && (form.landed_costs = [])"
                                />
                                <label class="form-check-label" for="show_landed_costs_edit">
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
                                <button
                                    v-if="isDraft"
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="addLandedCost">
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
                                        <th v-if="isDraft" class="text-center landed-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.landed_costs.length">
                                        <td :colspan="isDraft ? 7 : 6" class="text-center text-muted py-3">
                                            No additional charges added.
                                        </td>
                                    </tr>
                                    <tr v-for="(cost, index) in form.landed_costs" :key="index">
                                        <td>
                                            <template v-if="isDraft">
                                                <VInput
                                                    input-class="form-control form-control-sm"
                                                    v-model="form.landed_costs[index].cost_type"
                                                    placeholder="e.g. Transport"
                                                />
                                                <VInput
                                                    input-class="form-control form-control-sm mt-1"
                                                    v-model="form.landed_costs[index].description"
                                                    placeholder="Note (optional)"
                                                />
                                            </template>
                                            <template v-else>
                                                <div>{{ cost.cost_type || '—' }}</div>
                                                <div v-if="cost.description" class="text-muted small">{{ cost.description }}</div>
                                            </template>
                                        </td>
                                        <td>
                                            <VSelect
                                                v-if="isDraft"
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].treatment"
                                                :options="treatmentOptions"
                                            />
                                            <span v-else>{{ treatmentLabel(cost.treatment) }}</span>
                                        </td>
                                        <td>
                                            <template v-if="isDraft">
                                                <VSelect
                                                    v-if="cost.treatment !== 'expense'"
                                                    select-class="form-select form-select-sm"
                                                    v-model="form.landed_costs[index].allocation_method"
                                                    :options="allocationOptions"
                                                />
                                                <span v-else class="text-muted small">—</span>
                                            </template>
                                            <span v-else>
                                                {{ cost.treatment === 'expense' ? '—' : allocationLabel(cost.allocation_method) }}
                                            </span>
                                        </td>
                                        <td>
                                            <VInput
                                                v-if="isDraft"
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.landed_costs[index].amount"
                                                :min-value="0"
                                            />
                                            <span v-else class="d-block text-end">{{ formatMoney(cost.amount) }}</span>
                                        </td>
                                        <td>
                                            <template v-if="isDraft">
                                                <VInput
                                                    input-type="number"
                                                    input-class="form-control form-control-sm text-end"
                                                    v-model="form.landed_costs[index].vat_amount"
                                                    :min-value="0"
                                                />
                                                <div v-if="Number(form.landed_costs[index].vat_amount) > 0" class="d-flex align-items-center gap-1 mt-1">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input mt-0 flex-shrink-0"
                                                        :id="`lc_claim_${index}`"
                                                        v-model="form.landed_costs[index].vat_claim"
                                                    />
                                                    <label :for="`lc_claim_${index}`" class="small text-muted mb-0" style="cursor:pointer">Claim input VAT</label>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="text-end">{{ formatMoney(cost.vat_amount) }}</div>
                                                <div v-if="Number(cost.vat_amount) > 0" class="small mt-1" :class="cost.vat_claim ? 'text-success' : 'text-muted'">
                                                    {{ cost.vat_claim ? 'VAT claimed' : 'VAT not claimed' }}
                                                </div>
                                            </template>
                                        </td>
                                        <td>
                                            <VSelect
                                                v-if="isDraft"
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].account_id"
                                                :options="accounts.data"
                                                placeholder="Account"
                                            />
                                            <span v-else>{{ cost.account_name || '—' }}</span>
                                        </td>
                                        <td v-if="isDraft" class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLandedCost(index)">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    </tbody>
                                    <tfoot v-if="form.landed_costs.length" class="table-secondary fw-bold">
                                    <tr>
                                        <td colspan="3" class="text-end">Charge Total</td>
                                        <td class="text-end">{{ formatMoney(landedCostSummary.amount) }}</td>
                                        <td class="text-end">{{ formatMoney(landedCostSummary.vat) }}</td>
                                        <td :colspan="isDraft ? 2 : 1"></td>
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
                                    <li v-if="!isDraft">
                                        <h4>Discount</h4>
                                        <h5>{{ formatMoney(summary.totalDiscount) }}</h5>
                                    </li>
                                    <li v-else class="po-total-order-discount">
                                        <h4>Discount</h4>
                                        <div class="po-total-order-discount__controls">
                                            <VDiscountAmountTypeGroup selector-mode="toggle"
                                                v-model="form.order_discount_value"
                                                v-model:discount-type="form.order_discount_type"
                                                :error="errors.order_discount_value"
                                                input-id="bill_edit_order_discount_value"
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
                                :disabled="!isDraft"
                                @validate="validateField('remarks')"
                                :error="errors.remarks"
                            />
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button @click="closeEditModal" class="btn btn-cancel" type="button">
                                Cancel
                            </button>
                            <VButton v-if="isDraft" :loading="isSubmitting" :disabled="isSubmitting"/>
                            <button v-else type="button" class="btn btn-secondary" disabled>
                                Approved
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
import {computed, nextTick, reactive, ref, toRef, watch} from 'vue';
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
import {lineDiscountMoneyFromItem, lineNetFromItem, orderDiscountMoney, buildOrderAllocations} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useLineItemTaxOptions, parseTaxSelection, taxSelectionValue} from '@/composables/useLineItemTaxOptions.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchLineInput from '@/components/inventory/BatchLineInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import CreateSupplier from '@/views/admin/party/Create.vue';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {useGrnStore} from '@/stores/admin/inventory/grn.js';

const billStore = useBillStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();
const accountStore = useAccountStore();
const grnStore = useGrnStore();

const edit_bill_id = defineModel('bill_id');
const createSupplierOpened = ref(false);
const showLandedCosts = ref(false);

const {bill} = storeToRefs(billStore);
const {parties} = storeToRefs(partyStore);
const {taxes, taxGroups} = storeToRefs(taxStore);
const {warehouses, stockLocationOptionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);
const {accounts} = storeToRefs(accountStore);

const lineTaxOptions = useLineItemTaxOptions(taxes, taxGroups);

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

const treatmentLabel = (value) => treatmentOptions.find((o) => o.id === value)?.name ?? value ?? '—';
const allocationLabel = (value) => allocationOptions.find((o) => o.id === value)?.name ?? value ?? '—';

const newLandedCostTemplate = () => ({
    cost_type: '',
    description: '',
    treatment: 'capitalized',
    allocation_method: 'value',
    amount: 0,
    vat_amount: 0,
    vat_claim: true,
    account_id: '',
    account_name: '',
});

const debouncedSupplierSearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'supplier',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

const initialState = {
    bill_date: '',
    due_date: '',
    party_id: '',
    warehouse_id: '',
    supplier_invoice_no: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
    landed_costs: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);
const isHydratingBill = ref(false);

const documentParty = computed(() => bill.value.data?.party ?? null);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties, documentParty);

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

function rateStringFromApiLine(item) {
    if (item.rate !== null && item.rate !== undefined && item.rate !== '') {
        return String(Number(item.rate));
    }
    if (item.product_variant) {
        return defaultLineRateString(item.product_variant);
    }
    return '0';
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

watch(
    () => edit_bill_id.value,
    async (id) => {
        if (!id) {
            return;
        }
        taxStore.getTaxes();
        taxStore.getTaxGroups();
        warehouseStore.getWarehouses();
        accountStore.getAccounts();
        await billStore.getBill(id);
        const data = bill.value.data;
        await partyStore.getParties({
            filter: {
                type: 'supplier',
                limit: 50,
                search: data.party_name || '',
            },
        });
        const pid = data.party_id;
        const pname = data.party_name;
        if (pid && pname && !partyStore.parties.data.some((p) => String(p.id) === String(pid))) {
            partyStore.parties.data = [{id: pid, name: pname}, ...partyStore.parties.data];
        }

        const whId = data.items?.[0]?.warehouse_id;
        const whName = data.items?.[0]?.warehouse?.name;
        if (whId && whName && !warehouseStore.warehouses.data.some((w) => String(w.id) === String(whId))) {
            warehouseStore.warehouses.data = [{id: whId, name: whName}, ...warehouseStore.warehouses.data];
        }

        isHydratingBill.value = true;
        Object.keys(form).forEach((key) => {
            if (key === 'items') {
                form.items = (data.items || []).map((item) => ({
                    id: item.id,
                    product_variant_id: item.product_variant_id || '',
                    product_label: item.product_variant ? variantLabel(item.product_variant) : '',
                    grn_item_id: item.grn_item_id || null,
                    grn_no: item.grn_no || '',
                    grn_landed_costs: item.grn_item_id ? (data.grn_landed_costs || []) : [],
                    list_sale_snapshot: item.product_variant?.sales_price ?? 0,
                    unit_id: item.unit_id || '',
                    quantity: String(item.quantity ?? '1'),
                    rate: rateStringFromApiLine(item),
                    tax_id: taxSelectionValue({tax_id: item.tax_id, tax_group_id: item.tax_group_id}),
                    tax_line_type: item.tax_line_type || 'taxable',
                    line_discount_type: item.line_discount_type || 'fixed',
                    line_discount_value: String(
                        item.line_discount_value != null
                            ? item.line_discount_value
                            : (item.discount_amount ?? 0)
                    ),
                    is_batch_tracked: !!item.product_variant?.is_batch_tracked,
                    create_batch: false,
                    batch_id: item.batch_id || null,
                    batch_no: item.batch_no || '',
                    batch: item.batch || null,
                    mfg_date: item.mfg_date || null,
                    expiry_date: item.expiry_date || null,
                }));
            } else if (key === 'warehouse_id') {
                form.warehouse_id = whId || '';
            } else {
                form[key] = data[key] ?? (key === 'items' ? [] : '');
            }
        });
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value =
            data.order_discount_value != null && data.order_discount_value !== ''
                ? String(data.order_discount_value)
                : '0';
        showLandedCosts.value = (data.landed_costs || []).length > 0;
        form.landed_costs = (data.landed_costs || []).map((cost) => ({
            cost_type: cost.cost_type || '',
            description: cost.description || '',
            treatment: cost.treatment || 'capitalized',
            allocation_method: cost.allocation_method || 'value',
            amount: cost.amount ?? 0,
            vat_amount: cost.vat_amount ?? 0,
            vat_claim: Number(cost.vat_claimable_amount ?? 0) > 0,
            account_id: cost.account_id || '',
            account_name: cost.account?.name || '',
        }));
        await nextTick();
        isHydratingBill.value = false;
    }
);

const isDraft = computed(() => bill.value.data.status === 'draft');
const hasGrnLines = computed(() => form.items.some((item) => item.grn_item_id));
const canEnterLandedCosts = computed(() => isDraft.value && !hasGrnLines.value);

const grnLandedCosts = computed(() => {
    const fromItems = [];
    form.items.forEach((item) => {
        (item.grn_landed_costs || []).forEach((cost) => fromItems.push(cost));
    });
    if (fromItems.length) {
        const map = new Map();
        fromItems.forEach((cost) => map.set(`${cost.id}-${cost.cost_type}`, cost));
        return [...map.values()];
    }
    return bill.value.data?.grn_landed_costs || [];
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
        supplier_invoice_no: form.supplier_invoice_no || null,
        remarks: form.remarks,
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

const updateBill = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await billStore.updateBill(id, buildBillPayload());
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
    edit_bill_id.value = '';
};

function resetForm() {
    isHydratingBill.value = false;
    Object.assign(form, {...initialState});
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
.order-lines-table .po-col-total {
    min-width: 5.5rem;
}

.order-lines-table .po-col-action {
    width: 3rem;
}

.order-lines-table .po-col-batch {
    min-width: 10rem;
}

.po-line-total {
    font-weight: 600;
    font-size: 0.875rem;
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
</style>
