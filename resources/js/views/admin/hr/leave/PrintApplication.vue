<template>
    <VModal
        :show-modal="!!printData?.id"
        @close-click="close"
        modal-class="leave-application-print-modal"
        size="lg"
        title="Leave Application">
        <template #modal-body>
            <DocumentPrintLayout
                document-title="LEAVE APPLICATION"
                :document-no="documentNo"
                :document-date="documentDate"
            >
                <template #header-meta>
                    <p class="mb-1 fw-medium">
                        Status:
                        <span :class="statusBadge(status)">{{ printData.status_label }}</span>
                    </p>
                </template>

                <template #body>
                    <h6 class="text-muted mb-2">Employee Details</h6>
                    <DocumentPrintMetaGrid :rows="employeeRows" />

                    <h6 class="text-muted mb-2 mt-4">Leave Details</h6>
                    <DocumentPrintMetaGrid :rows="leaveRows" />

                    <div class="mt-4">
                        <h6 class="text-muted mb-2">Reason for Leave</h6>
                        <p class="border rounded p-3 mb-0">{{ printData.reason || '—' }}</p>
                    </div>

                    <div v-if="isRejected && printData.rejection_reason" class="mt-4">
                        <h6 class="text-muted mb-2">Rejection Remarks</h6>
                        <p class="border rounded p-3 mb-0 text-danger">{{ printData.rejection_reason }}</p>
                    </div>
                </template>

                <template #footer>
                    <DocumentPrintSignatures :labels="signatureLabels" class="mt-5" />
                </template>
            </DocumentPrintLayout>

            <div v-if="printData?.id" class="d-flex justify-content-end gap-2 mt-3 no-print">
                <button type="button" class="btn btn-light btn-sm" @click="close">Close</button>
                <DocumentPrintButton
                    target="#document-print-area"
                    :title="printTitle"
                    label="Print"
                    button-class="btn-sm"
                />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { computed, watch } from 'vue';
import VModal from '@/components/base/VModal.vue';
import DocumentPrintLayout from '@/components/print/DocumentPrintLayout.vue';
import DocumentPrintMetaGrid from '@/components/print/DocumentPrintMetaGrid.vue';
import DocumentPrintSignatures from '@/components/print/DocumentPrintSignatures.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';
import { useCompanyBranding } from '@/composables/useCompanyBranding.js';

const printData = defineModel('printData', { type: Object, default: () => ({}) });

const { ensureBranding } = useCompanyBranding();

const status = computed(() => printData.value.status?.value ?? printData.value.status ?? '');
const isRejected = computed(() => status.value === 'rejected');

const employee = computed(() => printData.value.employee || {});
const leaveType = computed(() => printData.value.leave_type || {});

const documentNo = computed(() => (printData.value.id ? `LA-${String(printData.value.id).padStart(5, '0')}` : ''));
const documentDate = computed(() => printData.value.from_date || '');

const printTitle = computed(() => `Leave Application - ${employee.value.full_name || ''}`);

const employeeRows = computed(() => [
    [
        { label: 'Name', value: employee.value.full_name },
        { label: 'Code', value: employee.value.employee_code },
    ],
    [
        { label: 'Department', value: employee.value.department?.name },
        { label: 'Designation', value: employee.value.designation?.name },
    ],
]);

const leaveRows = computed(() => [
    [
        { label: 'Leave Type', value: leaveType.value.name },
        { label: 'Total Days', value: formatDays(printData.value.days) },
    ],
    [
        { label: 'From', value: dateWithBs(printData.value.from_date, printData.value.bs_from_date) },
        { label: 'To', value: dateWithBs(printData.value.to_date, printData.value.bs_to_date) },
    ],
]);

const signatureLabels = computed(() => {
    const approver = printData.value.approved_by_name;

    if (status.value === 'approved') {
        return ['Applicant', 'Approved By' + (approver ? ` (${approver})` : ''), 'HR / Admin'];
    }

    return ['Applicant', 'Approved By', 'HR / Admin'];
});

const formatDays = (days) => {
    if (days === null || days === undefined || days === '') {
        return '—';
    }

    return `${days} ${Number(days) === 1 ? 'day' : 'days'}`;
};

const dateWithBs = (ad, bs) => {
    if (!ad) {
        return '—';
    }

    return bs ? `${ad} (${bs} BS)` : ad;
};

const statusBadge = (s) => ({
    pending: 'badge bg-warning text-dark',
    approved: 'badge bg-success',
    rejected: 'badge bg-danger',
}[s] ?? 'badge bg-secondary');

const close = () => { printData.value = {}; };

watch(() => printData.value?.id, (id) => {
    if (id) {
        ensureBranding();
    }
});
</script>
