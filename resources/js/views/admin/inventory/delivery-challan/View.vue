<template>
    <PageHeader :title="`Challan: ${challan?.challan_no || ''}`" subtitle="Delivery Challan Detail">
        <template #actions>
            <DocumentPrintButton
                v-if="challan"
                target="#document-print-area"
                title="Delivery Challan"
                label="Print"
                button-class="btn-outline-primary me-2"
            />
            <router-link :to="{ name: 'admin.delivery-challan-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button
                v-if="challan?.status === 'approved'"
                v-can="'create_invoice'"
                class="btn btn-primary me-2 no-print"
                @click="openInvoiceModal">
                <i class="ti ti-file-invoice me-1"></i> Create Invoice
            </button>
            <button
                v-if="challan?.status === 'draft'"
                v-can="'approve_delivery_challan'"
                class="btn btn-success no-print"
                @click="approve"
                :disabled="approving">
                <span v-if="approving" class="spinner-border spinner-border-sm me-1"></span>
                Approve & Issue Stock
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
        <span class="spinner-border"></span>
    </div>

    <DocumentPrintLayout
        v-else-if="challan"
        document-title="Delivery Challan"
        :document-no="challan.challan_no || ''"
        :document-date="formatDate(challan.challan_date)"
    >
        <template #header-meta>
            <span class="badge" :class="challan.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'">
                {{ challan.status }}
            </span>
        </template>

        <template #parties>
            <DocumentPrintParties
                :party-name="challan.party?.name"
                :party-address="challan.delivery_address"
            />
        </template>

        <template #body>
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Warehouse:</strong> {{ challan.warehouse?.name || '—' }}</p>
                    <p class="mb-1"><strong>Receiver:</strong> {{ challan.receiver_name || '—' }}</p>
                    <p v-if="challan.sales_order_id" class="mb-0"><strong>Sales Order:</strong> #{{ challan.sales_order_id }}</p>
                </div>
                <div class="col-md-6 no-print">
                    <p class="mb-1"><strong>Created By:</strong> {{ challan.create_user?.name || '—' }}</p>
                    <p class="mb-1"><strong>Approved By:</strong> {{ challan.approve_user?.name || '—' }}</p>
                    <p class="mb-0"><strong>Approved At:</strong> {{ formatDate(challan.approved_at) }}</p>
                </div>
            </div>
            <p v-if="challan.remarks" class="mb-3"><strong>Remarks:</strong> {{ challan.remarks }}</p>

            <h6 class="mb-2">Items</h6>
            <div class="table-responsive">
                <table class="table table-sm datanew table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Total</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in challan.challan_items" :key="item.id">
                            <td>{{ idx + 1 }}</td>
                            <td>{{ item.product_variant?.product?.name || item.product_variant?.name }}</td>
                            <td>{{ item.product_variant?.sku || '—' }}</td>
                            <td class="text-end">{{ item.quantity }}</td>
                            <td class="text-end">{{ formatMoney(item.rate) }}</td>
                            <td class="text-end fw-semibold">{{ formatMoney(item.quantity * item.rate) }}</td>
                            <td>{{ item.remarks || '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="5">Grand Total</td>
                            <td class="text-end">{{ formatMoney(grandTotal) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-if="challan.status === 'approved' && stockMovements.length" class="mt-4 no-print">
                <h6 class="mb-2">Stock Movements</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Product Variant</th>
                                <th class="text-end">Quantity</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="movement in stockMovements" :key="movement.id">
                                <td>{{ movement.product_variant_id }}</td>
                                <td class="text-end">{{ movement.quantity }}</td>
                                <td>{{ movement.type }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </DocumentPrintLayout>

    <CreateInvoice
        v-model:create-modal-opened="invoiceModalOpened"
        v-model:delivery-challan-id="invoiceDeliveryChallanId"
    />
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, ref, onMounted} from 'vue';
import {useRoute} from 'vue-router';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import {useDeliveryChallanStore} from '@/stores/admin/inventory/delivery-challan.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import CreateInvoice from '@/views/admin/sales/invoice/Create.vue';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintParties from '@/components/print/DocumentPrintParties.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const route = useRoute();
const deliveryChallanStore = useDeliveryChallanStore();
const {ensureBranding} = useCompanyBranding();

const challan = ref(null);
const loading = ref(false);
const approving = ref(false);
const invoiceModalOpened = ref(false);
const invoiceDeliveryChallanId = ref('');

const stockMovements = computed(() => challan.value?.stock_movements || []);

const grandTotal = computed(() =>
    (challan.value?.challan_items || []).reduce(
        (sum, item) => sum + Number(item.quantity || 0) * Number(item.rate || 0),
        0,
    ),
);

const loadChallan = async () => {
    loading.value = true;
    try {
        await ensureBranding();
        const res = await deliveryChallanStore.getChallan(route.params.id);
        challan.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const approve = async () => {
    approving.value = true;
    try {
        const res = await deliveryChallanStore.approveChallan(challan.value.id);
        toast('success', res.data.message);
        await loadChallan();
    } catch (e) {
        showErrors(e);
    } finally {
        approving.value = false;
    }
};

const openInvoiceModal = () => {
    invoiceDeliveryChallanId.value = String(challan.value.id);
    invoiceModalOpened.value = true;
};

onMounted(() => { loadChallan(); });
</script>
