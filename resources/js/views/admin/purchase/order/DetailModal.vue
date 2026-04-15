<template>
    <VModal
        :show-modal="!!detailOrderId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Purchase order detail">
        <template #modal-body>
            <VLoader v-if="order.loading" loader-type="progress"/>
            <div v-else-if="detailData.id" class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <div class="sales-details-items d-flex flex-wrap gap-3 mb-4">
                        <div class="details-item">
                            <h6>Supplier</h6>
                            <p class="mb-0">{{ detailData.party_name || '—' }}</p>
                        </div>
                        <div class="details-item">
                            <h6>Order</h6>
                            <p class="mb-0">
                                {{ detailData.order_no }}<br>
                                {{ detailData.order_date }}<br>
                                <span
                                    class="badge"
                                    :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                    {{ detailData.status }}
                                </span>
                            </p>
                        </div>
                        <div class="details-item">
                            <h6>Remarks</h6>
                            <p class="mb-0">{{ detailData.remarks || '—' }}</p>
                        </div>
                    </div>
                    <h5 class="order-text mb-3">Order summary</h5>
                    <div class="table-responsive no-pagination">
                        <table class="table datanew table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>SN</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Rate</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th>Tax amt</th>
                                <th>Line total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in (detailData.items || [])" :key="item.id || index">
                                <td>{{ index + 1 }}</td>
                                <td class="text-start">{{ productLabel(item) }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ formatN(item.rate) }}</td>
                                <td>{{ formatN(item.discount_amount) }}</td>
                                <td>{{ taxLabel(item) }}</td>
                                <td>{{ formatN(item.tax_amount) }}</td>
                                <td>{{ formatN(lineTotal(item)) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="row mt-3">
                        <div class="col-lg-6 ms-auto">
                            <div class="total-order w-100 max-widthauto m-auto mb-2">
                                <ul>
                                    <li>
                                        <h4>Sub total</h4>
                                        <h5>{{ formatN(detailData.subtotal) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Discount</h4>
                                        <h5>{{ formatN(detailData.discount_total) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Tax</h4>
                                        <h5>{{ formatN(detailData.tax_total) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Grand total</h4>
                                        <h5>{{ formatN(detailData.grand_total) }}</h5>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {usePurchaseOrderStore} from '@/stores/admin/purchase/purchase-order.js';

const purchaseOrderStore = usePurchaseOrderStore();
const {order} = storeToRefs(purchaseOrderStore);

const detailOrderId = defineModel('detailOrderId', {type: String, default: ''});

const detailData = computed(() => order.value.data || {});

watch(
    () => detailOrderId.value,
    (id) => {
        if (id) {
            purchaseOrderStore.getOrder(id);
        }
    }
);

const closeModal = () => {
    detailOrderId.value = '';
};

const formatN = (v) => {
    if (v === null || v === undefined || v === '') {
        return '—';
    }
    return Number(v).toFixed(2);
};

const productLabel = (item) => {
    if (item.product_variant?.name) {
        return item.product_variant.name;
    }
    return '—';
};

const taxLabel = (item) => {
    if (item.tax?.name) {
        const r = item.tax.rate != null ? `${item.tax.rate}%` : '';
        return r ? `${item.tax.name} (${r})` : item.tax.name;
    }
    return '—';
};

const lineTotal = (item) => {
    const qty = Number(item.quantity || 0);
    const rate = Number(item.rate || 0);
    const disc = Number(item.discount_amount || 0);
    const taxAmt = Number(item.tax_amount || 0);
    return qty * rate - disc + taxAmt;
};
</script>
