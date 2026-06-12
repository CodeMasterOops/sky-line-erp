<template>
    <VModal
        :show-modal="!!detailCreditNoteId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Credit note detail">
        <template #modal-body>
            <VLoader v-if="creditNote.loading" loader-type="progress"/>
            <AdjustmentNotePrintLayout
                v-else-if="detailData.id"
                document-title="CREDIT NOTE"
                :detail-data="detailData"
                party-title="Customer"
            />

            <div v-if="detailData.id && !creditNote.loading" class="d-flex flex-wrap gap-2 mt-3 no-print">
                <DocumentPrintButton
                    target="#document-print-area"
                    title="Credit Note"
                    label="Print"
                    button-class="btn-sm"
                />
                <button
                    v-can="'approve_credit_note'"
                    v-if="detailData.status === 'approved' && !detailData.voided_at"
                    type="button"
                    class="btn btn-warning btn-sm text-dark"
                    @click="voidCreditNote">
                    <i class="ti ti-ban me-1"></i>Void credit note
                </button>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useCreditNoteStore} from '@/stores/admin/sales/credit-note.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import AdjustmentNotePrintLayout from '@/components/print/AdjustmentNotePrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const emit = defineEmits(['voided']);

const creditNoteStore = useCreditNoteStore();
const {creditNote} = storeToRefs(creditNoteStore);
const {ensureBranding} = useCompanyBranding();

const detailCreditNoteId = defineModel('detailCreditNoteId', {type: String, default: ''});

const detailData = computed(() => creditNote.value.data || {});

watch(
    () => detailCreditNoteId.value,
    async (id) => {
        if (id) {
            await ensureBranding();
            creditNoteStore.getCreditNote(id);
        }
    },
);

const closeModal = () => {
    detailCreditNoteId.value = '';
};

const voidCreditNote = async () => {
    const id = detailData.value.id;
    if (!id) {
        return;
    }
    Swal.fire({
        title: 'Void credit note?',
        text: 'This reverses return inventory and marks the credit note void.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await creditNoteStore.voidCreditNote(id);
                toast(res.status, res.data?.message ?? 'Credit note voided.');
                emit('voided');
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
