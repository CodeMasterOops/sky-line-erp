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

        <div v-if="contextFields.length" class="form-document-context-row mb-3">
            <p v-for="field in contextFields" :key="field.label" class="mb-1">
                <strong>{{ field.label }}:</strong> {{ field.value || '—' }}
            </p>
        </div>

        <p v-if="detailData.remarks" class="form-document-context-row mb-3">
            <strong>Remarks:</strong> {{ detailData.remarks }}
        </p>

        <table class="form-document-ledger-table">
            <thead>
            <tr>
                <th style="width: 5%">SN</th>
                <th>Product</th>
                <th style="width: 12%">SKU</th>
                <th class="text-end" style="width: 10%">{{ qtyColumnLabel }}</th>
                <th v-if="showUnit" style="width: 10%">Unit</th>
                <th v-if="showRates" class="text-end" style="width: 12%">Rate</th>
                <th v-if="showRates" class="text-end" style="width: 12%">Amount</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(item, index) in lineItems" :key="item.id || index">
                <td class="text-center">{{ index + 1 }}</td>
                <td>{{ productName(item) }}</td>
                <td>{{ item.product_variant?.sku || '—' }}</td>
                <td class="text-end">{{ qtyValue(item) }}</td>
                <td v-if="showUnit">{{ itemUnitLabel(item) }}</td>
                <td v-if="showRates" class="text-end">{{ formatMoney(item.rate ?? item.unit_cost) }}</td>
                <td v-if="showRates" class="text-end">{{ formatMoney(lineAmount(item)) }}</td>
            </tr>
            </tbody>
            <tfoot v-if="showRates && showGrandTotal">
            <tr>
                <td :colspan="showUnit ? 6 : 5" class="text-end">Grand Total</td>
                <td class="text-end">{{ formatMoney(grandTotal) }}</td>
            </tr>
            </tfoot>
        </table>

        <p v-if="footerNote" class="form-document-note">{{ footerNote }}</p>

        <DocumentPrintSignatures :labels="['Delivered By', 'Received By']" />
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
        default: 'Party',
    },
    documentNoKey: {
        type: String,
        default: 'challan_no',
    },
    documentDateKey: {
        type: String,
        default: 'challan_date',
    },
    itemsKey: {
        type: String,
        default: 'items',
    },
    qtyColumnLabel: {
        type: String,
        default: 'Qty',
    },
    showRates: {
        type: Boolean,
        default: false,
    },
    showUnit: {
        type: Boolean,
        default: true,
    },
    showGrandTotal: {
        type: Boolean,
        default: false,
    },
    footerNote: {
        type: String,
        default: '',
    },
    contextFields: {
        type: Array,
        default: () => [],
    },
});

const lineItems = computed(() => props.detailData[props.itemsKey] || []);

const partyName = computed(() =>
    props.detailData.party_name || props.detailData.party?.name || '',
);

const partyAddress = computed(() =>
    props.detailData.party_address || props.detailData.delivery_address || props.detailData.party?.address || '',
);

const partyPhone = computed(() =>
    props.detailData.party_phone || props.detailData.party?.phone || '',
);

const partyPan = computed(() =>
    props.detailData.party_pan || props.detailData.party?.pan || '',
);

const metaRows = computed(() => {
    const rows = [[
        {label: 'Document No', value: props.detailData[props.documentNoKey]},
        {label: 'Date', value: props.detailData[props.documentDateKey]},
    ]];

    const secondRow = [];
    if (props.detailData.warehouse?.name || props.detailData.warehouse_name) {
        secondRow.push({
            label: 'Warehouse',
            value: props.detailData.warehouse?.name || props.detailData.warehouse_name,
        });
    }
    if (props.detailData.reference_label && props.detailData.reference_value) {
        secondRow.push({
            label: props.detailData.reference_label,
            value: props.detailData.reference_value,
        });
    }
    if (props.detailData.status) {
        secondRow.push({label: 'Status', value: formatStatus(props.detailData.status)});
    }

    if (secondRow.length) {
        rows.push(secondRow);
    }

    return rows;
});

const grandTotal = computed(() => {
    if (props.detailData.grand_total != null) {
        return Number(props.detailData.grand_total);
    }

    return lineItems.value.reduce((sum, item) => sum + lineAmount(item), 0);
});

function productName(item) {
    return item.product_variant?.product?.name || item.product_variant?.name || '—';
}

function qtyValue(item) {
    return item.quantity ?? item.received_qty ?? item.ordered_qty ?? '—';
}

function lineAmount(item) {
    const qty = Number(item.received_qty ?? item.quantity ?? 0);
    const rate = Number(item.unit_cost ?? item.rate ?? 0);

    return qty * rate;
}

function formatStatus(status) {
    if (!status) {
        return '—';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>
