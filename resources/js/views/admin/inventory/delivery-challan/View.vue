<template>
    <PageHeader :title="`Challan: ${challan?.challan_no || ''}`" subtitle="Delivery Challan Detail">
        <template #actions>
            <router-link :to="{ name: 'admin.delivery-challan-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button v-if="challan?.status === 'draft'" class="btn btn-success" @click="approve" :disabled="approving">
                <span v-if="approving" class="spinner-border spinner-border-sm me-1"></span>
                Approve & Issue Stock
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5"><span class="spinner-border"></span></div>

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
                                <tr><td class="text-muted">Status</td>
                                    <td><span class="badge" :class="challan.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'">{{ challan.status }}</span></td>
                                </tr>
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
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Rate</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in challan.challan_items" :key="item.id">
                                <td>{{ idx + 1 }}</td>
                                <td>{{ item.product_variant?.product?.name }}</td>
                                <td>{{ item.product_variant?.sku }}</td>
                                <td class="text-end">{{ item.quantity }}</td>
                                <td class="text-end">{{ fmt(item.rate) }}</td>
                                <td class="text-end fw-semibold">{{ fmt(item.quantity * item.rate) }}</td>
                            </tr>
                        </tbody>
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
const challan = ref(null);
const loading = ref(false);
const approving = ref(false);

const loadChallan = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin(`delivery-challan/${route.params.id}`, 'get');
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
        const res = await apiAdmin(`delivery-challan/${challan.value.id}/approve`, 'post');
        toast('success', res.data.message);
        await loadChallan();
    } catch (e) {
        showErrors(e);
    } finally {
        approving.value = false;
    }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

onMounted(() => { loadChallan(); });
</script>
