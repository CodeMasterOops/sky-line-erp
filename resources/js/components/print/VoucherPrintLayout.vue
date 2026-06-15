<template>
    <FormDocumentShell>
        <DocumentPrintFormHeader :document-title="documentTitle" />

        <DocumentPrintMetaGrid :rows="metaRows" />

        <div v-if="hasContextFields" class="form-document-context-row">
            <p v-if="accountLabel && accountName">
                <strong>{{ accountLabel }}:</strong> {{ accountName }}
            </p>
            <p v-if="counterpartyLabel && counterpartyName">
                <strong>{{ counterpartyLabel }}:</strong> {{ counterpartyName }}
            </p>
            <p v-if="detailData.remarks">
                <strong>Narration:</strong> {{ detailData.remarks }}
            </p>
        </div>

        <table class="form-document-ledger-table">
            <thead>
            <tr>
                <th style="width: 5%">SN</th>
                <th>Particulars</th>
                <th v-if="showDebitCredit" class="text-end" style="width: 14%">Debit</th>
                <th v-if="showDebitCredit" class="text-end" style="width: 14%">Credit</th>
                <th v-else class="text-end" style="width: 14%">Amount</th>
                <th style="width: 22%">Remarks</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(item, index) in lineItems" :key="item.id || index">
                <td class="text-center">{{ index + 1 }}</td>
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
            <tr>
                <td colspan="2" class="text-end">Total</td>
                <td class="text-end">{{ formatMoney(totalDebit) }}</td>
                <td class="text-end">{{ formatMoney(totalCredit) }}</td>
                <td></td>
            </tr>
            </tfoot>
            <tfoot v-else>
            <tr>
                <td colspan="2" class="text-end">Total</td>
                <td class="text-end">{{ formatMoney(totalAmount) }}</td>
                <td></td>
            </tr>
            </tfoot>
        </table>

        <DocumentPrintAmountWords :amount="wordsAmount" />

        <DocumentPrintSignatures />
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
    counterpartyLabel: {
        type: String,
        default: '',
    },
    counterpartyName: {
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

const wordsAmount = computed(() =>
    props.showDebitCredit ? totalDebit.value : totalAmount.value,
);

const metaRows = computed(() => [
    [
        {label: 'Voucher No', value: props.detailData.voucher_no},
        {label: 'Date', value: props.detailData.date},
        {label: 'Reference No', value: props.detailData.reference_no},
    ],
    [
        {label: 'Status', value: formatStatus(props.detailData.status)},
    ],
]);

const hasContextFields = computed(() =>
    (props.accountLabel && props.accountName)
    || (props.counterpartyLabel && props.counterpartyName)
    || props.detailData.remarks,
);

function formatStatus(status) {
    if (!status) {
        return '—';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>
