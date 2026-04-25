<template>
    <PageHeader :title="`GRN: ${grn?.grn_no || ''}`" subtitle="Goods Received Note Detail">
        <template #actions>
            <router-link :to="{ name: 'admin.grn-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button v-if="grn?.status === 'draft'" class="btn btn-success" @click="approve" :disabled="approving">
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
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted">GRN No</td><td class="fw-semibold">{{ grn.grn_no }}</td></tr>
                                <tr><td class="text-muted">Supplier</td><td>{{ grn.party?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Warehouse</td><td>{{ grn.warehouse?.name || '-' }}</td></tr>
                                <tr><td class="text-muted">Received Date</td><td>{{ formatDate(grn.received_date) }}</td></tr>
                                <tr><td class="text-muted">Supplier Invoice</td><td>{{ grn.supplier_invoice_no || '-' }}</td></tr>
                                <tr><td class="text-muted">Status</td>
                                    <td><span class="badge" :class="grn.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'">{{ grn.status }}</span></td>
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
            <div class="card-header"><h6 class="mb-0">Items</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
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
                                <td>{{ item.product_variant?.product?.name }}</td>
                                <td>{{ item.product_variant?.sku }}</td>
                                <td class="text-end">{{ item.ordered_qty }}</td>
                                <td class="text-end">{{ item.received_qty }}</td>
                                <td class="text-end">{{ fmt(item.unit_cost) }}</td>
                                <td class="text-end fw-semibold">{{ fmt(item.received_qty * item.unit_cost) }}</td>
                                <td>{{ item.batch_no || '-' }}</td>
                                <td>{{ formatDate(item.expiry_date) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td colspan="6">Total</td>
                                <td class="text-end">{{ fmt(grn.grn_items?.reduce((s, i) => s + i.received_qty * i.unit_cost, 0)) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {useRoute} from 'vue-router';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';

const route = useRoute();
const grn = ref(null);
const loading = ref(false);
const approving = ref(false);

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

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

onMounted(() => { loadGrn(); });
</script>
