<template>
    <VModal
        :show-modal="!!detailDebitNoteId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Debit note detail">
        <template #modal-body>
            <VLoader v-if="debitNote.loading" loader-type="progress"/>
            <AdjustmentNotePrintLayout
                v-else-if="detailData.id"
                document-title="DEBIT NOTE"
                :detail-data="detailData"
                party-title="Supplier"
                document-no-key="debit_note_no"
                document-date-key="debit_note_date"
                original-no-key="bill_no"
                original-date-key="bill_date"
            />

            <div v-if="detailData.id && !debitNote.loading" class="d-flex flex-wrap gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Debit Note" label="Print" button-class="btn-sm" />
                <button
                    v-can="'approve_debit_note'"
                    v-if="detailData.status === 'approved' && !detailData.voided_at"
                    type="button"
                    class="btn btn-warning btn-sm text-dark"
                    @click="voidDebitNote">
                    <i class="ti ti-ban me-1"></i>Void debit note
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
import {useDebitNoteStore} from '@/stores/admin/purchase/debit-note.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import AdjustmentNotePrintLayout from '@/components/print/AdjustmentNotePrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const emit = defineEmits(['voided']);

const debitNoteStore = useDebitNoteStore();
const {debitNote} = storeToRefs(debitNoteStore);
const {ensureBranding} = useCompanyBranding();

const detailDebitNoteId = defineModel('detailDebitNoteId', {type: String, default: ''});
const detailData = computed(() => debitNote.value.data || {});

watch(() => detailDebitNoteId.value, async (id) => {
    if (id) {
        await ensureBranding();
        debitNoteStore.getDebitNote(id);
    }
});

const closeModal = () => { detailDebitNoteId.value = ''; };

const voidDebitNote = async () => {
    const id = detailData.value.id;
    if (!id) return;
    Swal.fire({
        title: 'Void debit note?',
        text: 'This reverses inventory and marks the debit note void.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Void',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await debitNoteStore.voidDebitNote(id);
                toast(res.status, res.data?.message ?? 'Debit note voided.');
                emit('voided');
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
