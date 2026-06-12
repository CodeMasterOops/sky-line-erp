<template>
    <DocumentPrintLayout
        :document-title="documentTitle"
        :document-no="detailData.voucher_no || ''"
        :document-date="detailData.date || ''"
    >
        <template #header-meta>
            <p class="mb-1">Reference: {{ detailData.reference_no || '—' }}</p>
            <span class="badge" :class="detailData.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                {{ detailData.status }}
            </span>
        </template>

        <template #body>
            <p v-if="accountLabel" class="mb-3"><strong>{{ accountLabel }}:</strong> {{ accountName }}</p>
            <p v-if="detailData.remarks" class="mb-3"><strong>Remarks:</strong> {{ detailData.remarks }}</p>

            <div class="table-responsive">
                <table class="table datanew table-bordered mb-0">
                    <thead>
                    <tr>
                        <th>SN</th>
                        <th>Account</th>
                        <th v-if="showDebitCredit" class="text-end">Debit</th>
                        <th v-if="showDebitCredit" class="text-end">Credit</th>
                        <th v-else class="text-end">Amount</th>
                        <th>Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item, index) in lineItems" :key="item.id || index">
                        <td>{{ index + 1 }}</td>
                        <td>{{ item.account || '—' }}</td>
                        <template v-if="showDebitCredit">
                            <td class="text-end">{{ formatMoney(item.dr_amount) }}</td>
                            <td class="text-end">{{ formatMoney(item.cr_amount) }}</td>
                        </template>
                        <td v-else class="text-end">{{ formatMoney(item.amount) }}</td>
                        <td>{{ item.remarks || '—' }}</td>
                    </tr>
                    </tbody>
                    <tfoot v-if="showDebitCredit">
                        <tr class="fw-bold">
                            <td colspan="2" class="text-end">Total</td>
                            <td class="text-end">{{ formatMoney(totalDebit) }}</td>
                            <td class="text-end">{{ formatMoney(totalCredit) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <tfoot v-else>
                        <tr class="fw-bold">
                            <td colspan="2" class="text-end">Total</td>
                            <td class="text-end">{{ formatMoney(totalAmount) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </template>
    </DocumentPrintLayout>
</template>

<script setup>
import {computed} from 'vue';
import {formatMoney} from '@/helpers/formatMoney.js';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';

const props = defineProps({
    documentTitle: {
        type: String,
        required: true,
    },
    detailData: {
        type: Object,
        default: () => ({}),
    },
    accountLabel: {
        type: String,
        default: '',
    },
    accountName: {
        type: String,
        default: '',
    },
    showDebitCredit: {
        type: Boolean,
        default: false,
    },
});

const lineItems = computed(() => props.detailData.items || []);

const totalAmount = computed(() =>
    lineItems.value.reduce((s, i) => s + Number(i.amount || 0), 0),
);

const totalDebit = computed(() =>
    lineItems.value.reduce((s, i) => s + Number(i.dr_amount || 0), 0),
);

const totalCredit = computed(() =>
    lineItems.value.reduce((s, i) => s + Number(i.cr_amount || 0), 0),
);
</script>
