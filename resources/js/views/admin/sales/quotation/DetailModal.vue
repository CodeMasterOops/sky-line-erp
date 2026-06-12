<template>
    <VModal
        :show-modal="!!detailQuotationId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Quotation detail">
        <template #modal-body>
            <VLoader v-if="quotation.loading" loader-type="progress"/>
            <DocumentPrintLayout
                v-else-if="detailData.id"
                document-title="Quotation"
                :document-no="detailData.quotation_no || ''"
                :document-date="detailData.quotation_date || ''"
            >
                <template #header-meta>
                    <p class="mb-1">Expiry: {{ detailData.expiry_date || '—' }}</p>
                    <span class="badge" :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                        {{ detailData.status }}
                    </span>
                </template>

                <template #parties>
                    <DocumentPrintParties :party-name="detailData.party_name" />
                </template>

                <template #body>
                    <p v-if="detailData.remarks" class="mb-3"><strong>Remarks:</strong> {{ detailData.remarks }}</p>
                    <div class="table-responsive">
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
                            <tr v-for="(item, index) in (detailData.items || [])" :key="item.id || index">
                                <td>{{ index + 1 }}</td>
                                <td>{{ item.product_variant?.name || '—' }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ formatMoney(item.rate) }}</td>
                                <td>{{ formatMoney(item.discount_amount) }}</td>
                                <td>{{ formatMoney(item.tax_amount) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #totals>
                    <div class="col-lg-6 ms-auto">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between mb-2"><span>Sub total</span><strong>{{ formatMoney(totals.subtotal) }}</strong></li>
                            <li class="d-flex justify-content-between mb-2"><span>Discount</span><strong>{{ formatMoney(totals.discount) }}</strong></li>
                            <li class="d-flex justify-content-between mb-2"><span>Tax</span><strong>{{ formatMoney(totals.tax) }}</strong></li>
                            <li class="d-flex justify-content-between border-top pt-2"><span>Grand total</span><strong>{{ formatMoney(totals.grandTotal) }}</strong></li>
                        </ul>
                    </div>
                </template>
            </DocumentPrintLayout>

            <div v-if="detailData.id && !quotation.loading" class="d-flex gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Quotation" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {useQuotationStore} from '@/stores/admin/sales/quotation.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintParties from '@/components/print/DocumentPrintParties.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const quotationStore = useQuotationStore();
const {quotation} = storeToRefs(quotationStore);
const {ensureBranding} = useCompanyBranding();

const detailQuotationId = defineModel('detailQuotationId', {type: String, default: ''});
const detailData = computed(() => quotation.value.data || {});

const totals = computed(() => {
    const items = detailData.value.items || [];
    const subtotal = items.reduce((s, i) => s + Number(i.quantity || 0) * Number(i.rate || 0), 0);
    const lineDiscount = items.reduce((s, i) => s + Number(i.discount_amount || 0), 0);
    const orderDiscount = Number(detailData.value.order_discount_amount || 0);
    const tax = items.reduce((s, i) => s + Number(i.tax_amount || 0), 0);
    const discount = lineDiscount + orderDiscount;
    return {subtotal, discount, tax, grandTotal: subtotal - discount + tax};
});

watch(() => detailQuotationId.value, async (id) => {
    if (id) {
        await ensureBranding();
        quotationStore.getQuotation(id);
    }
});

const closeModal = () => { detailQuotationId.value = ''; };
</script>
