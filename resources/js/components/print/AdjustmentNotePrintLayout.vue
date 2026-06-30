<template>
    <FormDocumentShell>
        <DocumentPrintFormHeader :document-title="documentTitle" />

        <DocumentPrintMetaGrid :rows="metaRows" />

        <DocumentPrintPartyBox
            :title="partyTitle"
            :name="partyName"
            :address="partyAddress"
            :phone="partyPhone"
            :pan="partyPan"
        />

        <p v-if="detailData.remarks" class="form-document-context-row mb-3">
            <strong>Reason / Remarks:</strong> {{ detailData.remarks }}
        </p>

        <table class="form-document-ledger-table">
            <thead>
            <tr>
                <th style="width: 5%">SN</th>
                <th>Description</th>
                <th class="text-end" style="width: 10%">Qty</th>
                <th class="text-end" style="width: 14%">Rate</th>
                <th class="text-end" style="width: 14%">Amount</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(item, index) in lineItems" :key="item.id || index">
                <td class="text-center">{{ index + 1 }}</td>
                <td>{{ itemLabel(item) }}</td>
                <td class="text-end">{{ item.quantity ?? '—' }}</td>
                <td class="text-end">{{ formatMoney(item.rate) }}</td>
                <td class="text-end">{{ formatMoney(lineAmount(item)) }}</td>
            </tr>
            </tbody>
        </table>

        <div class="form-document-summary-box mb-3">
            <table>
                <tbody>
                <tr v-for="row in summaryRows" :key="row.label">
                    <td>{{ row.label }}</td>
                    <td>{{ formatMoney(row.value) }}</td>
                </tr>
                </tbody>
            </table>
        </div>

        <DocumentPrintAmountWords :amount="grandTotal" />

        <DocumentPrintSignatures :labels="['Authorized Signatory']" />
    </FormDocumentShell>
</template>

<script setup>
import {computed} from 'vue';
import {formatMoney} from '@/helpers/formatMoney.js';
import FormDocumentShell from '@/components/print/FormDocumentShell.vue';
import DocumentPrintFormHeader from '@/components/print/DocumentPrintFormHeader.vue';
import DocumentPrintMetaGrid from '@/components/print/DocumentPrintMetaGrid.vue';
import DocumentPrintPartyBox from '@/components/print/DocumentPrintPartyBox.vue';
import DocumentPrintAmountWords from '@/components/print/DocumentPrintAmountWords.vue';
import DocumentPrintSignatures from '@/components/print/DocumentPrintSignatures.vue';

const props = defineProps({
    documentTitle: {
        type: String,
        required: true,
    },
    detailData: {
        type: Object,
        default: () => ({}),
    },
    partyTitle: {
        type: String,
        default: 'Customer',
    },
    documentNoKey: {
        type: String,
        default: 'credit_note_no',
    },
    documentDateKey: {
        type: String,
        default: 'credit_note_date',
    },
    originalNoKey: {
        type: String,
        default: 'invoice_no',
    },
    originalDateKey: {
        type: String,
        default: 'invoice_date',
    },
});

const lineItems = computed(() => props.detailData.items || []);

const partyName = computed(() => props.detailData.party_name || '');
const partyAddress = computed(() => props.detailData.party_address || '');
const partyPhone = computed(() => props.detailData.party_phone || '');
const partyPan = computed(() => props.detailData.party_pan || '');

const grandTotal = computed(() => Number(props.detailData.grand_total || 0));

const metaRows = computed(() => [
    [
        {label: 'Note No', value: props.detailData[props.documentNoKey]},
        {label: 'Date', value: props.detailData[props.documentDateKey]},
        {label: originalNoLabel.value, value: props.detailData[props.originalNoKey]},
    ],
    [
        {label: originalDateLabel.value, value: props.detailData[props.originalDateKey]},
        {label: 'Status', value: formatStatus(props.detailData.status)},
    ],
]);

const originalNoLabel = computed(() =>
    props.originalNoKey === 'bill_no' ? 'Original Bill No' : 'Original Invoice No',
);

const originalDateLabel = computed(() =>
    props.originalDateKey === 'bill_date' ? 'Original Bill Date' : 'Original Invoice Date',
);

const taxGroupSubtotals = computed(() => {
    const map = new Map();
    for (const item of lineItems.value) {
        const taxAmt = Number(item.tax_amount || 0);
        if (taxAmt === 0) { continue; }
        const label = item.tax_group?.name || item.tax?.name || 'Tax';
        map.set(label, (map.get(label) ?? 0) + taxAmt);
    }
    return [...map.entries()].map(([label, amount]) => ({label, amount}));
});

const summaryRows = computed(() => {
    const taxRows = taxGroupSubtotals.value.length > 0
        ? taxGroupSubtotals.value.map((tg) => ({label: tg.label, value: tg.amount}))
        : (Number(props.detailData.tax_total || 0) > 0
            ? [{label: 'Tax', value: props.detailData.tax_total}]
            : []);

    return [
        {label: 'Subtotal', value: props.detailData.subtotal},
        {label: 'Discount', value: props.detailData.discount_total},
        ...taxRows,
        {label: 'Grand Total', value: props.detailData.grand_total},
    ].filter((row) => row.value != null && row.value !== '');
});

function itemLabel(item) {
    return item.product_variant?.name || item.product_variant?.product?.name || '—';
}

function lineAmount(item) {
    const qty = Number(item.quantity || 0);
    const rate = Number(item.rate || 0);
    const discount = Number(item.discount_amount || 0);
    const tax = Number(item.tax_amount || 0);

    return qty * rate - discount + tax;
}

function formatStatus(status) {
    if (!status) {
        return '—';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>
