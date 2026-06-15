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
            <strong>Remarks:</strong> {{ detailData.remarks }}
        </p>

        <table class="form-document-ledger-table">
            <thead>
            <tr>
                <th style="width: 5%">SN</th>
                <th>Description</th>
                <th class="text-end" style="width: 10%">Qty</th>
                <th style="width: 10%">Unit</th>
                <th class="text-end" style="width: 14%">Rate</th>
                <th class="text-end" style="width: 14%">Amount</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(item, index) in lineItems" :key="item.id || index">
                <td class="text-center">{{ index + 1 }}</td>
                <td>{{ itemLabel(item) }}</td>
                <td class="text-end">{{ item.quantity ?? '—' }}</td>
                <td>{{ itemUnitLabel(item) }}</td>
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

        <p class="form-document-disclaimer">This document is not a tax invoice.</p>

        <DocumentPrintSignatures :labels="['Authorized Signatory']" />
    </FormDocumentShell>
</template>

<script setup>
import {computed} from 'vue';
import {formatMoney} from '@/helpers/formatMoney.js';
import {itemUnitLabel} from '@/helpers/formatUnitLabel.js';
import FormDocumentShell from '@/components/print/FormDocumentShell.vue';
import DocumentPrintFormHeader from '@/components/print/DocumentPrintFormHeader.vue';
import DocumentPrintMetaGrid from '@/components/print/DocumentPrintMetaGrid.vue';
import DocumentPrintPartyBox from '@/components/print/DocumentPrintPartyBox.vue';
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
        default: 'order_no',
    },
    documentDateKey: {
        type: String,
        default: 'order_date',
    },
    extraMeta: {
        type: Array,
        default: () => [],
    },
});

const lineItems = computed(() => props.detailData.items || []);

const partyName = computed(() => props.detailData.party_name || '');
const partyAddress = computed(() => props.detailData.party_address || '');
const partyPhone = computed(() => props.detailData.party_phone || '');
const partyPan = computed(() => props.detailData.party_pan || '');

const metaRows = computed(() => {
    const firstRow = [
        {label: 'Document No', value: props.detailData[props.documentNoKey]},
        {label: 'Date', value: props.detailData[props.documentDateKey]},
    ];

    props.extraMeta.forEach((meta) => {
        if (firstRow.length < 3) {
            firstRow.push({label: meta.label, value: props.detailData[meta.key]});
        }
    });

    const rows = [firstRow];

    if (props.detailData.reference_no || props.detailData.status) {
        rows.push([
            {label: 'Reference', value: props.detailData.reference_no},
            {label: 'Status', value: formatStatus(props.detailData.status)},
        ]);
    }

    return rows;
});

const summaryRows = computed(() => {
    const rows = [
        {label: 'Subtotal', value: props.detailData.subtotal},
        {label: 'Discount', value: props.detailData.discount_total ?? props.detailData.discount},
    ];

    if (props.detailData.order_discount_amount != null && Number(props.detailData.order_discount_amount) !== 0) {
        rows.push({label: 'Order discount', value: props.detailData.order_discount_amount});
    }

    if (props.detailData.non_taxable_base != null) {
        rows.push({label: 'Non-taxable (net)', value: props.detailData.non_taxable_base});
    }

    if (props.detailData.taxable_base != null) {
        rows.push({label: 'Taxable (net)', value: props.detailData.taxable_base});
    }

    rows.push(
        {label: 'Tax', value: props.detailData.tax_total ?? props.detailData.tax},
        {label: 'Grand Total', value: props.detailData.grand_total ?? props.detailData.grandTotal},
    );

    return rows.filter((row) => row.value != null && row.value !== '');
});

function itemLabel(item) {
    return item.product_variant?.name || item.product_variant?.product?.name || item.description || '—';
}

function lineAmount(item) {
    const qty = Number(item.quantity || 0);
    const rate = Number(item.rate || 0);
    const discount = Number(item.discount_amount || 0);
    const tax = Number(item.tax_amount || 0);

    if (item.line_total != null) {
        return item.line_total;
    }

    return qty * rate - discount + tax;
}

function formatStatus(status) {
    if (!status) {
        return '—';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>
