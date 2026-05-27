<template>
    <PageHeader :title="`Challan: ${challan?.challan_no || ''}`" subtitle="Delivery Challan Detail">
        <template #actions>
            <router-link :to="{ name: 'admin.delivery-challan-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button
                v-if="challan?.status === 'approved'"
                v-can="'create_invoice'"
                class="btn btn-primary me-2"
                @click="openInvoiceModal">
                <i class="ti ti-file-invoice me-1"></i> Create Invoice
            </button>
            <button
                v-if="challan?.status === 'draft'"
                v-can="'approve_delivery_challan'"
                class="btn btn-success"
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

    <div v-else-if="challan">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted">Challan No</td><td class="fw-semibold">{{ challan.challan_no }}</td></tr>
                                <tr><td class="text-muted">Customer</td><td>{{ challan.party?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Warehouse</td><td>{{ challan.warehouse?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Date</td><td>{{ formatDate(challan.challan_date) }}</td></tr>
                                <tr><td class="text-muted">Receiver</td><td>{{ challan.receiver_name || '-' }}</td></tr>
                                <tr><td class="text-muted">Delivery Address</td><td>{{ challan.delivery_address || '-' }}</td></tr>
                                <tr v-if="challan.sales_order_id">
                                    <td class="text-muted">Sales Order</td>
                                    <td>#{{ challan.sales_order_id }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status</td>
                                    <td>
                                        <span class="badge" :class="challan.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'">
                                            {{ challan.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted">Created By</td><td>{{ challan.create_user?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Approved By</td><td>{{ challan.approve_user?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Approved At</td><td>{{ formatDate(challan.approved_at) }}</td></tr>
                                <tr><td class="text-muted">Remarks</td><td>{{ challan.remarks || '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 mb-4">
            <div class="card-header"><h6 class="mb-0">Items</h6></div>
            <div class="card-body">
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
                                <td>{{ item.product_variant?.sku || '-' }}</td>
                                <td class="text-end">{{ item.quantity }}</td>
                                <td class="text-end">{{ fmt(item.rate) }}</td>
                                <td class="text-end fw-semibold">{{ fmt(item.quantity * item.rate) }}</td>
                                <td>{{ item.remarks || '-' }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td colspan="5">Grand Total</td>
                                <td class="text-end">{{ fmt(grandTotal) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="challan.status === 'approved' && stockMovements.length" class="card border-0">
            <div class="card-header"><h6 class="mb-0">Stock Movements</h6></div>
            <div class="card-body">
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
        </div>
    </div>

    <CreateInvoice
        v-model:create-modal-opened="invoiceModalOpened"
        v-model:delivery-challan-id="invoiceDeliveryChallanId"
    />
</template>

<script setup>
import {computed, ref, onMounted} from 'vue';
import {useRoute} from 'vue-router';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import {useDeliveryChallanStore} from '@/stores/admin/inventory/delivery-challan.js';
import CreateInvoice from '@/views/admin/sales/invoice/Create.vue';

const route = useRoute();
const deliveryChallanStore = useDeliveryChallanStore();

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

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

const openInvoiceModal = () => {
    invoiceDeliveryChallanId.value = String(challan.value.id);
    invoiceModalOpened.value = true;
};

onMounted(() => { loadChallan(); });
</script>
