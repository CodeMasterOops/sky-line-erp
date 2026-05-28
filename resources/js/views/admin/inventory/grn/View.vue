<template>
    <PageHeader :title="`GRN: ${grn?.grn_no || ''}`" subtitle="Goods Received Note Detail">
        <template #actions>
            <router-link :to="{ name: 'admin.grn-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button
                v-if="grn?.status === 'draft'"
                type="button"
                class="btn btn-outline-primary me-2"
                @click="editGrnId = grn.id">
                <i class="ti ti-edit me-1"></i> Edit
            </button>
            <button
                v-if="grn?.status === 'draft'"
                type="button"
                class="btn btn-success"
                @click="approve"
                :disabled="approving">
                <span v-if="approving" class="spinner-border spinner-border-sm me-1"></span>
                Approve & Receive Stock
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
        <span class="spinner-border"></span>
    </div>

    <div v-else-if="grn">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h6 class="mb-0">Receipt Details</h6>
                    </div>
                    <div class="card-body p-0 border-0 pt-2">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted w-40">GRN No</td><td class="fw-semibold">{{ grn.grn_no }}</td></tr>
                                <tr><td class="text-muted">Supplier</td><td>{{ grn.party?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Warehouse</td><td>{{ grn.warehouse?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Received Date</td><td>{{ formatDate(grn.received_date) }}</td></tr>
                                <tr><td class="text-muted">Supplier Invoice</td><td>{{ grn.supplier_invoice_no || '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h6 class="mb-0">Status & Audit</h6>
                    </div>
                    <div class="card-body p-0 border-0 pt-2">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted w-40">Status</td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="grn.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                            {{ grn.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Billing</td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info text-capitalize">
                                            {{ billingLabel(grn.billing_status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr><td class="text-muted">Created By</td><td>{{ grn.create_user?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Approved By</td><td>{{ grn.approve_user?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Approved At</td><td>{{ formatDate(grn.approved_at) }}</td></tr>
                                <tr><td class="text-muted">Remarks</td><td>{{ grn.remarks || '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0">
            <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Line Items</h6>
                <span class="text-muted small">{{ grn.grn_items?.length || 0 }} item(s)</span>
            </div>
            <div class="card-body p-0 border-0">
                <div class="table-responsive">
                    <table class="table datanew table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end">Ordered</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Billed</th>
                                <th class="text-end">Remaining</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total</th>
                                <th>Batch</th>
                                <th>Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in grn.grn_items" :key="item.id">
                                <td>{{ idx + 1 }}</td>
                                <td class="fw-medium">{{ item.product_variant?.product?.name || item.product_variant?.name }}</td>
                                <td>{{ item.product_variant?.sku || '-' }}</td>
                                <td class="text-end">{{ item.ordered_qty }}</td>
                                <td class="text-end">{{ item.received_qty }}</td>
                                <td class="text-end">{{ item.billed_qty ?? 0 }}</td>
                                <td class="text-end">{{ remainingQty(item) }}</td>
                                <td class="text-end">{{ formatMoney(item.unit_cost) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoney(item.received_qty * item.unit_cost) }}</td>
                                <td>{{ item.batch_no || '-' }}</td>
                                <td>{{ formatDate(item.expiry_date) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td colspan="8" class="text-end">Grand Total</td>
                                <td class="text-end">{{ formatMoney(grandTotal) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 mt-4">
            <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Additional Charges / Landed Costs</h6>
                <span class="text-muted small">{{ grn.landed_costs?.length || 0 }} charge(s)</span>
            </div>
            <div class="card-body p-0 border-0">
                <div class="table-responsive">
                    <table class="table datanew table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Treatment</th>
                                <th>Allocation</th>
                                <th>Account</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">VAT</th>
                                <th class="text-end">Claimable VAT</th>
                                <th class="text-end">Allocated</th>
                                <th>Journal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!grn.landed_costs?.length">
                                <td colspan="10" class="text-center text-muted py-3">
                                    No additional charges recorded.
                                </td>
                            </tr>
                            <tr v-for="(cost, idx) in grn.landed_costs" :key="cost.id">
                                <td>{{ idx + 1 }}</td>
                                <td>
                                    <div class="fw-medium">{{ cost.cost_type }}</div>
                                    <small v-if="cost.description" class="text-muted">{{ cost.description }}</small>
                                </td>
                                <td class="text-capitalize">{{ cost.treatment }}</td>
                                <td>{{ cost.treatment === 'expense' ? '-' : allocationLabel(cost.allocation_method) }}</td>
                                <td>{{ cost.account?.name || (cost.account_id ? `#${cost.account_id}` : '-') }}</td>
                                <td class="text-end">{{ formatMoney(cost.amount) }}</td>
                                <td class="text-end">{{ formatMoney(cost.vat_amount) }}</td>
                                <td class="text-end">{{ formatMoney(cost.vat_claimable_amount) }}</td>
                                <td class="text-end">{{ formatMoney(allocatedTotal(cost)) }}</td>
                                <td>{{ cost.journal_id ? `JV #${cost.journal_id}` : '-' }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="grn.landed_costs?.length" class="table-secondary fw-bold">
                            <tr>
                                <td colspan="5" class="text-end">Charge Total</td>
                                <td class="text-end">{{ formatMoney(landedCostSummary.amount) }}</td>
                                <td class="text-end">{{ formatMoney(landedCostSummary.vat) }}</td>
                                <td class="text-end">{{ formatMoney(landedCostSummary.claimableVat) }}</td>
                                <td class="text-end">{{ formatMoney(landedCostSummary.allocated) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <EditGrn v-model:grn-id="editGrnId" @saved="loadGrn" />
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {computed, ref, onMounted} from 'vue';
import {useRoute} from 'vue-router';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import EditGrn from './Edit.vue';

const route = useRoute();
const grn = ref(null);
const loading = ref(false);
const approving = ref(false);
const editGrnId = ref('');

const billingLabel = (status) => (status || 'open').replace(/_/g, ' ');

const remainingQty = (item) =>
    Math.max(0, Number(item.received_qty || 0) - Number(item.billed_qty || 0));

const grandTotal = computed(() =>
    (grn.value?.grn_items || []).reduce(
        (sum, item) => sum + Number(item.received_qty || 0) * Number(item.unit_cost || 0),
        0
    )
);

const landedCostSummary = computed(() =>
    (grn.value?.landed_costs || []).reduce((summary, cost) => {
        summary.amount += Number(cost.amount || 0);
        summary.vat += Number(cost.vat_amount || 0);
        summary.claimableVat += Number(cost.vat_claimable_amount || 0);
        summary.allocated += allocatedTotal(cost);

        return summary;
    }, {amount: 0, vat: 0, claimableVat: 0, allocated: 0})
);

const allocatedTotal = (cost) =>
    (cost.allocations || []).reduce((sum, allocation) => sum + Number(allocation.allocated_amount || 0), 0);

const allocationLabel = (method) => ({
    value: 'By Value',
    quantity: 'By Quantity',
    equal: 'Equal',
}[method] || method || '-');

const loadGrn = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin(`grn/${route.params.id}`, 'get');
        grn.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const approve = async () => {
    approving.value = true;
    try {
        const res = await apiAdmin(`grn/${grn.value.id}/approve`, 'post');
        toast('success', res.data.message);
        await loadGrn();
    } catch (e) {
        showErrors(e);
    } finally {
        approving.value = false;
    }
};


onMounted(() => {
    loadGrn();
});
</script>

<style scoped>
.w-40 {
    width: 40%;
}
</style>
