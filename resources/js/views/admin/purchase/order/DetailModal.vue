<template>
    <VModal
        :show-modal="!!detailOrderId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Purchase order detail">
        <template #modal-body>
            <VLoader v-if="order.loading" loader-type="progress"/>
            <DocumentPrintLayout
                v-else-if="detailData.id"
                document-title="Purchase Order"
                :document-no="detailData.order_no || ''"
                :document-date="detailData.order_date || ''"
            >
                <template #header-meta>
                    <span class="badge" :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                        {{ detailData.status }}
                    </span>
                </template>

                <template #parties>
                    <DocumentPrintParties :party-name="detailData.party_name" />
                </template>

                <template #body>
                    <p v-if="detailData.remarks" class="mb-3"><strong>Remarks:</strong> {{ detailData.remarks }}</p>
                    <h5 class="order-text mb-3">Line items</h5>
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
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in (detailData.items || [])" :key="item.id ?? index">
                                <td>{{ index + 1 }}</td>
                                <td class="text-start">{{ productLabel(item) }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ formatMoney(item.rate) }}</td>
                                <td>{{ formatMoney(item.discount_amount) }}</td>
                                <td>{{ taxLabel(item) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #totals>
                    <div class="col-lg-6 ms-auto">
                        <div class="total-order w-100 max-widthauto m-auto mb-2">
                            <ul>
                                <li><h4>Sub total</h4><h5>{{ formatMoney(detailData.subtotal) }}</h5></li>
                                <li><h4>Discount</h4><h5>{{ formatMoney(detailTotalDiscount) }}</h5></li>
                                <li><h4>Non-taxable (net)</h4><h5>{{ formatMoney(detailData.non_taxable_base) }}</h5></li>
                                <li><h4>Taxable (net)</h4><h5>{{ formatMoney(detailData.taxable_base) }}</h5></li>
                                <li><h4>Tax</h4><h5>{{ formatMoney(detailData.tax_total) }}</h5></li>
                                <li><h4>Grand total</h4><h5>{{ formatMoney(detailData.grand_total) }}</h5></li>
                            </ul>
                        </div>
                    </div>
                </template>
            </DocumentPrintLayout>

            <div v-if="detailData.id && !order.loading" class="d-flex flex-wrap gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Purchase Order" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {usePurchaseOrderStore} from '@/stores/admin/purchase/purchase-order.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintParties from '@/components/print/DocumentPrintParties.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const purchaseOrderStore = usePurchaseOrderStore();
const {order} = storeToRefs(purchaseOrderStore);
const {ensureBranding} = useCompanyBranding();

const detailOrderId = defineModel('detailOrderId', {type: String, default: ''});
const detailData = computed(() => order.value.data || {});

const detailTotalDiscount = computed(() => {
    const d = detailData.value;
    const line = Number(d.discount_total ?? d.line_discount_total ?? 0) || 0;
    const orderAmt = Number(d.order_discount_amount ?? 0) || 0;
    return line + orderAmt;
});

watch(() => detailOrderId.value, async (id) => {
    if (id) {
        await ensureBranding();
        purchaseOrderStore.getOrder(id);
    }
});

const closeModal = () => { detailOrderId.value = ''; };
const productLabel = (item) => item.product_variant?.name || '—';
const taxLabel = (item) => {
    if (item.tax?.name) {
        const r = item.tax.rate != null ? `${item.tax.rate}%` : '';
        return r ? `${item.tax.name} (${r})` : item.tax.name;
    }
    return '—';
};
</script>
