<template>
    <VModal
        :show-modal="!!detailVoucherId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Receipt voucher detail">
        <template #modal-body>
            <VLoader v-if="voucher.loading" loader-type="progress"/>
            <VoucherPrintLayout
                v-else-if="detailData.id"
                document-title="Receipt Voucher"
                :detail-data="detailData"
                account-label="Deposited To"
                :account-name="detailData.deposited_to_account"
                counterparty-label="Received From"
                :counterparty-name="receivedFromAccount"
            />

            <div v-if="detailData.id && !voucher.loading" class="d-flex gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Receipt Voucher" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {useReceiptVoucherStore} from '@/stores/admin/accounting/receipt-voucher.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import VoucherPrintLayout from '@/components/print/VoucherPrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const voucherStore = useReceiptVoucherStore();
const {voucher} = storeToRefs(voucherStore);
const {ensureBranding} = useCompanyBranding();

const detailVoucherId = defineModel('detailVoucherId', {type: String, default: ''});
const detailData = computed(() => voucher.value.data || {});

const receivedFromAccount = computed(() =>
    (detailData.value.items || []).map((item) => item.account).filter(Boolean).join(', ') || '—',
);

watch(() => detailVoucherId.value, async (id) => {
    if (id) {
        await ensureBranding();
        voucherStore.getVoucher(id);
    }
});

const closeModal = () => { detailVoucherId.value = ''; };
</script>
