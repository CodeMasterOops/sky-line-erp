<template>
    <PageHeader :title="`GRN: ${grn?.grn_no || ''}`" subtitle="Goods Received Note Detail">
        <template #actions>
            <DocumentPrintButton
                v-if="grn"
                target="#document-print-area"
                title="Goods Received Note"
                label="Print"
                button-class="btn-outline-primary me-2"
            />
            <router-link :to="{ name: 'admin.grn-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button
                v-if="grn?.status === 'draft'"
                type="button"
                class="btn btn-outline-primary me-2 no-print"
                @click="editGrnId = grn.id">
                <i class="ti ti-edit me-1"></i> Edit
            </button>
            <button
                v-if="grn?.status === 'draft'"
                type="button"
                class="btn btn-success no-print"
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

    <DocumentPrintLayout
        v-else-if="grn"
        document-title="Goods Received Note"
        :document-no="grn.grn_no || ''"
        :document-date="formatDate(grn.received_date)"
    >
        <template #header-meta>
            <p class="mb-1"><strong>Supplier Invoice:</strong> {{ grn.supplier_invoice_no || '—' }}</p>
            <span class="badge" :class="grn.status === 'approved' ? 'bg-success' : 'bg-secondary'">{{ grn.status }}</span>
            <span class="badge bg-info-subtle text-info text-capitalize ms-1">{{ billingLabel(grn.billing_status) }}</span>
        </template>

        <template #parties>
            <DocumentPrintParties :party-name="grn.party?.name" />
        </template>

        <template #body>
            <p class="mb-3"><strong>Warehouse:</strong> {{ grn.warehouse?.name || '—' }}</p>
            <p v-if="grn.remarks" class="mb-3"><strong>Remarks:</strong> {{ grn.remarks }}</p>

            <h6 class="mb-2">Line Items</h6>
            <div class="table-responsive mb-4">
                <table class="table datanew table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Received</th>
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
                            <td>{{ item.product_variant?.sku || '—' }}</td>
                            <td class="text-end">{{ item.ordered_qty }}</td>
                            <td class="text-end">{{ item.received_qty }}</td>
                            <td class="text-end">{{ formatMoney(item.unit_cost) }}</td>
                            <td class="text-end fw-semibold">{{ formatMoney(item.received_qty * item.unit_cost) }}</td>
                            <td>{{ item.batch_no || '—' }}</td>
                            <td>{{ formatDate(item.expiry_date) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="6" class="text-end">Grand Total</td>
                            <td class="text-end">{{ formatMoney(grandTotal) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h6 class="mb-2">Additional Charges / Landed Costs</h6>
            <div class="table-responsive">
                <table class="table datanew table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Treatment</th>
                            <th>Account</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!grn.landed_costs?.length">
                            <td colspan="6" class="text-center text-muted py-3">No additional charges recorded.</td>
                        </tr>
                        <tr v-for="(cost, idx) in grn.landed_costs" :key="cost.id">
                            <td>{{ idx + 1 }}</td>
                            <td>{{ cost.cost_type }}</td>
                            <td class="text-capitalize">{{ cost.treatment }}</td>
                            <td>{{ cost.account?.name || '—' }}</td>
                            <td class="text-end">{{ formatMoney(cost.amount) }}</td>
                            <td class="text-end">{{ formatMoney(cost.vat_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="no-print mt-3 text-muted small">
                <p class="mb-1"><strong>Created By:</strong> {{ grn.create_user?.name || '—' }}</p>
                <p class="mb-0"><strong>Approved By:</strong> {{ grn.approve_user?.name || '—' }} · {{ formatDate(grn.approved_at) }}</p>
            </div>
        </template>
    </DocumentPrintLayout>

    <EditGrn v-model:grn-id="editGrnId" @saved="loadGrn" />
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, ref, onMounted} from 'vue';
import {useRoute} from 'vue-router';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import EditGrn from './Edit.vue';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintParties from '@/components/print/DocumentPrintParties.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const route = useRoute();
const {ensureBranding} = useCompanyBranding();

const grn = ref(null);
const loading = ref(false);
const approving = ref(false);
const editGrnId = ref('');

const billingLabel = (status) => (status || 'open').replace(/_/g, ' ');

const grandTotal = computed(() =>
    (grn.value?.grn_items || []).reduce(
        (sum, item) => sum + Number(item.received_qty || 0) * Number(item.unit_cost || 0),
        0,
    ),
);

const loadGrn = async () => {
    loading.value = true;
    try {
        await ensureBranding();
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
