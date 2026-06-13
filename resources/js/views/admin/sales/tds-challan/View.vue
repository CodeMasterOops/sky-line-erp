<template>
    <PageHeader title="TDS Challan" :subtitle="challanNo" @refresh="loadChallan">
        <template #actions>
            <router-link :to="{ name: 'admin.tds-challan-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button
                v-if="challan.data.status === 'pending'"
                type="button"
                class="btn btn-success"
                :disabled="submitting"
                @click="handleMarkSubmitted">
                <i class="ti ti-check me-1"></i> Mark Submitted
            </button>
        </template>
    </PageHeader>

    <VLoader v-if="challan.loading" loader-type="progress" />

    <template v-else-if="challan.data.id">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-semibold">Challan Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <span class="text-muted small">Challan No</span>
                                <div class="fw-medium">{{ challan.data.challan_no || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small">Date</span>
                                <div class="fw-medium">{{ challan.data.challan_date }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small">Party</span>
                                <div class="fw-medium">{{ challan.data.party_name || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small">Period Month</span>
                                <div class="fw-medium">{{ monthLabel(challan.data.period_month) }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small">Payment Date</span>
                                <div class="fw-medium">{{ challan.data.payment_date || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small">Bank Name</span>
                                <div class="fw-medium">{{ challan.data.bank_name || '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small">Total TDS Amount</span>
                                <div class="fw-semibold text-primary fs-5">{{ formatMoney(challan.data.total_tds_amount) }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small">Status</span>
                                <div>
                                    <span class="badge" :class="challan.data.status === 'submitted' ? 'bg-success' : 'bg-warning text-dark'">
                                        {{ challan.data.status }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="challan.data.remarks" class="col-12">
                                <span class="text-muted small">Remarks</span>
                                <div>{{ challan.data.remarks }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold">Deduction Items</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                <tr>
                                    <th class="text-center" style="width:3rem">SN</th>
                                    <th>Category</th>
                                    <th class="text-end">Base Amount</th>
                                    <th class="text-end">TDS Rate</th>
                                    <th class="text-end">TDS Amount</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="(item, i) in challan.data.items" :key="item.id">
                                    <td class="text-center">{{ i + 1 }}</td>
                                    <td>{{ item.tds_deduction?.tds_category_label || item.tds_deduction?.tds_category || '—' }}</td>
                                    <td class="text-end">{{ formatMoney(item.tds_deduction?.base_amount) }}</td>
                                    <td class="text-end">{{ item.tds_deduction?.tds_rate }}%</td>
                                    <td class="text-end fw-semibold">{{ formatMoney(item.amount) }}</td>
                                </tr>
                                </tbody>
                                <tfoot>
                                <tr class="bg-light fw-semibold">
                                    <td colspan="4" class="text-end">Total TDS</td>
                                    <td class="text-end">{{ formatMoney(challan.data.total_tds_amount) }}</td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import {computed, ref, watch} from 'vue';
import {useRoute} from 'vue-router';
import {storeToRefs} from 'pinia';
import {formatMoney} from '@/helpers/formatMoney.js';
import {toast} from '@/helpers/toast.js';
import {useTdsChallanStore} from '@/stores/admin/sales/tds-challan.js';
import {useConfirmAction} from '@/composables/useConfirmAction.js';

const route = useRoute();
const challanStore = useTdsChallanStore();
const {challan} = storeToRefs(challanStore);
const {confirmAction} = useConfirmAction();
const submitting = ref(false);

const challanNo = computed(() => challan.value.data?.challan_no ? `Challan #${challan.value.data.challan_no}` : '');

const monthLabels = {
    1: 'Shrawan', 2: 'Bhadra', 3: 'Ashwin', 4: 'Kartik',
    5: 'Mangsir', 6: 'Poush', 7: 'Magh', 8: 'Falgun',
    9: 'Chaitra', 10: 'Baisakh', 11: 'Jestha', 12: 'Ashadh',
};

function monthLabel(m) {
    return m ? `${monthLabels[m] || m} (${m})` : '—';
}

function loadChallan() {
    challanStore.getChallan(route.params.id);
}

watch(() => route.params.id, (id) => {
    if (id) {
        loadChallan();
    }
}, {immediate: true});

const handleMarkSubmitted = () => {
    confirmAction({
        title: 'Mark as Submitted?',
        text: 'This will mark the challan as submitted to IRD.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Submit',
        action: () => challanStore.markSubmitted(route.params.id),
        onSuccess: () => toast('success', 'Challan marked as submitted.'),
    });
};
</script>
