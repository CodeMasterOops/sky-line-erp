<template>
    <VModal
        :show-modal="!!detailOrderId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Sales order detail">
        <template #modal-body>
            <VLoader v-if="order.loading" loader-type="progress"/>
            <div v-else-if="detailData.id" class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <div class="sales-details-items d-flex flex-wrap gap-3 mb-4">
                        <div class="details-item">
                            <h6>Customer</h6>
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
                                <th title="Line discount (amount)">Line disc.</th>
                                <th>Tax</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in detailData.items" :key="item.id || index">
                                <td>{{ index + 1 }}</td>
                                <td class="text-start">{{ productLabel(item) }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ formatN(item.rate) }}</td>
                                <td>{{ formatN(item.discount_amount) }}</td>
                                <td>{{ taxLabel(item) }}</td>
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
                                    <li v-if="detailData.order_discount_amount != null && Number(detailData.order_discount_amount) !== 0">
                                        <h4>Order discount</h4>
                                        <h5>{{ formatN(detailData.order_discount_amount) }}</h5>
                                    </li>
                                    <li>
                                        <h4>Discount (total)</h4>
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
import {useSalesOrderStore} from '@/stores/admin/sales/sales-order.js';

const salesOrderStore = useSalesOrderStore();
const {order} = storeToRefs(salesOrderStore);

const detailOrderId = defineModel('detailOrderId', {type: String, default: ''});

const detailData = computed(() => order.value.data || {});

watch(
    () => detailOrderId.value,
    (id) => {
        if (id) {
            salesOrderStore.getOrder(id);
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

</script>
