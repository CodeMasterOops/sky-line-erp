<template>
    <FormDocumentShell>
        <DocumentPrintFormHeader document-title="MONEY RECEIPT" />

        <DocumentPrintMetaGrid :rows="metaRows" />

        <div class="form-document-receipt-body">
            <p class="mb-2">{{ bodyIntro }}</p>
            <p class="form-document-receipt-amount mb-0">Rs. {{ formattedAmount }}</p>
        </div>

        <DocumentPrintAmountWords :amount="detailData.total_amount" />

        <table v-if="allocations.length" class="form-document-ledger-table">
            <thead>
            <tr>
                <th style="width: 8%">SN</th>
                <th>{{ allocationLabel }}</th>
                <th style="width: 18%">Date</th>
                <th class="text-end" style="width: 18%">Amount</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(alloc, index) in allocations" :key="alloc.id || index">
                <td class="text-center">{{ index + 1 }}</td>
                <td>{{ allocationNo(alloc) }}</td>
                <td>{{ allocationDate(alloc) }}</td>
                <td class="text-end">{{ formatMoney(alloc.amount) }}</td>
            </tr>
            </tbody>
        </table>

        <p v-if="detailData.remarks" class="form-document-context-row mb-3">
            <strong>Remarks:</strong> {{ detailData.remarks }}
        </p>

        <DocumentPrintSignatures :labels="['Receiver', 'Authorized Signatory']" />
    </FormDocumentShell>
</template>

<script setup>
import {computed} from 'vue';
import {formatMoney} from '@/helpers/formatMoney.js';
import FormDocumentShell from '@/components/print/FormDocumentShell.vue';
import DocumentPrintFormHeader from '@/components/print/DocumentPrintFormHeader.vue';
import DocumentPrintMetaGrid from '@/components/print/DocumentPrintMetaGrid.vue';
import DocumentPrintAmountWords from '@/components/print/DocumentPrintAmountWords.vue';
import DocumentPrintSignatures from '@/components/print/DocumentPrintSignatures.vue';

const props = defineProps({
    detailData: {
        type: Object,
        default: () => ({}),
    },
    variant: {
        type: String,
        default: 'received',
        validator: (value) => ['received', 'paid'].includes(value),
    },
    allocationDocumentKey: {
        type: String,
        default: 'invoice',
    },
});

const formattedAmount = computed(() => formatMoney(props.detailData.total_amount));

const bodyIntro = computed(() => {
    const party = props.detailData.party_name || '—';

    if (props.variant === 'paid') {
        return `Paid to ${party} the sum of`;
    }

    return `Received with thanks from ${party} the sum of`;
});

const allocationLabel = computed(() => {
    if (props.allocationDocumentKey === 'bill') {
        return 'Bill No';
    }

    if (props.allocationDocumentKey === 'payable') {
        return 'Reference No';
    }

    return 'Invoice No';
});

const allocations = computed(() => props.detailData.allocations || []);

const metaRows = computed(() => [
    [
        {label: 'Receipt No', value: documentNo.value},
        {label: 'Date', value: documentDate.value},
        {label: 'Payment Method', value: paymentMethod.value},
    ],
    [
        {label: 'Account', value: props.detailData.account_name},
        {label: 'Reference', value: props.detailData.reference_no},
        {label: 'Status', value: formatStatus(props.detailData.status)},
    ],
]);

const documentNo = computed(() =>
    props.detailData.receipt_no || props.detailData.payment_no || '',
);

const documentDate = computed(() =>
    props.detailData.receipt_date || props.detailData.payment_date || '',
);

const paymentMethod = computed(() =>
    props.detailData.payment_method || props.detailData.payment_mode_name || '',
);

function allocationNo(alloc) {
    if (alloc.invoice?.invoice_no) {
        return alloc.invoice.invoice_no;
    }

    if (alloc.bill?.bill_no) {
        return alloc.bill.bill_no;
    }

    if (alloc.payable?.reference_no) {
        return alloc.payable.reference_no;
    }

    return alloc.invoice_id || alloc.payable_id || '—';
}

function allocationDate(alloc) {
    if (alloc.invoice?.invoice_date) {
        return alloc.invoice.invoice_date;
    }

    if (alloc.bill?.bill_date) {
        return alloc.bill.bill_date;
    }

    if (alloc.payable?.date) {
        return alloc.payable.date;
    }

    return '—';
}

function formatStatus(status) {
    if (!status) {
        return '—';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>
