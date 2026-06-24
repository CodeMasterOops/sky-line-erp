<template>
    <PageHeader title="PDC Cheque Management" subtitle="Post-dated cheques — payable &amp; receivable" @refresh="fetchCheques">
        <template #actions>
            <button v-can="'create_cheque'" type="button" class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Record Cheque
            </button>
        </template>
    </PageHeader>

    <!-- Summary Cards -->
    <div v-if="summary.length" class="row g-3 mb-3">
        <div class="col-md-3" v-for="card in summaryCards" :key="card.label">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div :class="['fs-2 me-3', card.color]"><i :class="card.icon"></i></div>
                        <div>
                            <div class="text-muted small">{{ card.label }}</div>
                            <div class="fw-bold">{{ formatMoney(card.total) }}</div>
                            <div class="small text-muted">{{ card.count }} cheque{{ card.count !== 1 ? 's' : '' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Due this week alert -->
    <div v-if="dueThisWeek.length" class="alert alert-warning d-flex align-items-center gap-2">
        <i class="ti ti-alert-circle fs-5"></i>
        <div>
            <strong>{{ dueThisWeek.length }} cheque{{ dueThisWeek.length !== 1 ? 's' : '' }} due this week:</strong>
            <span class="ms-2" v-for="c in dueThisWeek.slice(0, 3)" :key="c.id">
                {{ c.cheque_no }} ({{ c.cheque_date }}){{ dueThisWeek.indexOf(c) < dueThisWeek.slice(0, 3).length - 1 ? ',' : '' }}
            </span>
            <span v-if="dueThisWeek.length > 3" class="ms-1 text-muted">+ {{ dueThisWeek.length - 3 }} more</span>
        </div>
    </div>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search cheque no or party"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters"
        >
            <template #filters>
                <div class="dropdown me-2">
                    <a
                        href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown"
                    >
                        {{ filter.type ? (filter.type === 'receivable' ? 'Receivable' : 'Payable') : 'All Types' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('type', '')">All Types</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('type', 'receivable')">Receivable</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('type', 'payable')">Payable</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a
                        href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown"
                    >
                        {{ filter.status ? statusLabel(filter.status) : 'All Statuses' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('status', '')">All Statuses</a></li>
                        <li v-for="s in statuses" :key="s.value">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1" @click="setFilter('status', s.value)">{{ s.label }}</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="cheques"
                    :loading="loading"
                    :pagination="false"
                    row-key="id"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (listMeta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'type'">
                            <span :class="record.type === 'receivable' ? 'badge bg-success' : 'badge bg-danger'">
                                {{ record.type === 'receivable' ? 'Receivable' : 'Payable' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span :class="statusClass(record.status)" class="badge text-capitalize">{{ record.status }}</span>
                        </template>
                        <template v-else-if="column.key === 'amount'">
                            {{ formatMoney(record.amount) }}
                        </template>
                        <template v-else-if="column.key === 'cheque_date'">
                            {{ adToBsDate(record.cheque_date) }}
                        </template>
                        <template v-else-if="column.key === 'bank'">
                            {{ record.bank_name || record.bank_account?.bank_name || '—' }}
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action d-flex flex-wrap gap-1">
                                    <button
                                        v-if="record.status === 'pending'"
                                        class="btn btn-xs btn-outline-warning"
                                        @click="openActionModal('present', record)"
                                    >Present</button>
                                    <button
                                        v-if="['pending', 'presented'].includes(record.status)"
                                        class="btn btn-xs btn-outline-success"
                                        @click="openActionModal('clear', record)"
                                    >Clear</button>
                                    <button
                                        v-if="record.status === 'presented'"
                                        class="btn btn-xs btn-outline-danger"
                                        @click="openActionModal('bounce', record)"
                                    >Bounce</button>
                                    <button
                                        v-if="record.status !== 'cleared'"
                                        class="btn btn-xs btn-outline-secondary"
                                        @click="cancelCheque(record.id)"
                                    >Cancel</button>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="listMeta" />
            </div>
        </div>
    </div>

    <!-- Record Cheque Modal -->
    <VModal
        :show-modal="createModal"
        size="lg"
        title="Record PDC Cheque"
        @close-click="createModal = false">
        <template #modal-body>
            <form @submit.prevent="saveCheque" class="row g-3">
                <div class="col-md-6">
                    <VMultiselect
                        id="cheque_type"
                        v-model="form.type"
                        label="Type"
                        :options="typeOptions"
                        value-prop="value"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="cheque_party"
                        v-model="form.party_id"
                        :label="partyLabel"
                        :options="partyOptions"
                        :placeholder="partyLabel"
                        required
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        v-if="isReceivable"
                        id="bank_name"
                        v-model="form.bank_name"
                        label="Bank Name"
                        placeholder="e.g. Nabil Bank"
                        required
                    />
                    <VMultiselect
                        v-else
                        id="bank_account_id"
                        v-model="form.bank_account_id"
                        label="Our Bank Account"
                        placeholder="Bank account"
                        :options="bankAccountOptions"
                        required
                    />
                </div>
                <div class="col-md-4">
                    <VInput id="cheque_no" v-model="form.cheque_no" label="Cheque No" placeholder="e.g. 001234" required />
                </div>
                <div class="col-md-4">
                    <VInput id="cheque_amount" v-model="form.amount" label="Amount (NPR)" input-type="number" required />
                </div>
                <div class="col-md-6">
                    <VDatepicker id="cheque_date" label="Cheque Date" v-model="form.cheque_date" required />
                </div>
                <div class="col-md-6">
                    <VDatepicker id="deposit_date" :label="depositDateLabel" v-model="form.deposit_date" />
                </div>
                <div class="col-12">
                    <VTextarea id="cheque_remarks" v-model="form.remarks" label="Remarks" :rows="2" />
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="createModal = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save Cheque
                    </button>
                </div>
            </form>
        </template>
    </VModal>

    <!-- Action Modal (present / clear / bounce) -->
    <VModal
        :show-modal="actionModal.show"
        size="md"
        :title="actionModal.title"
        @close-click="actionModal.show = false">
        <template #modal-body>
            <form @submit.prevent="submitAction" class="row g-3">
                <div class="col-12">
                    <p class="text-muted small mb-0">
                        Cheque <strong>{{ actionModal.cheque?.cheque_no }}</strong>
                        · {{ formatMoney(actionModal.cheque?.amount) }}
                        · {{ actionModal.cheque?.party?.name }}
                    </p>
                </div>
                <div v-if="actionModal.type !== 'bounce'" class="col-12">
                    <VDatepicker
                        id="action_date"
                        :label="actionModal.type === 'present' ? 'Deposit / Presentation Date' : 'Cleared Date'"
                        v-model="actionModal.date"
                        required
                    />
                </div>
                <div v-if="actionModal.type === 'bounce'" class="col-12">
                    <VTextarea id="bounce_reason" v-model="actionModal.remarks" label="Bounce Reason" :rows="2" />
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="actionModal.show = false">Cancel</button>
                    <button
                        type="submit"
                        class="btn"
                        :class="actionModal.type === 'bounce' ? 'btn-danger' : 'btn-primary'"
                        :disabled="actionModal.saving"
                    >
                        <span v-if="actionModal.saving" class="spinner-border spinner-border-sm me-1"></span>
                        Confirm
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { adToBsDate } from '@/helpers/helper.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import VDatepicker from '@/components/base/VDatepicker.vue';

const loading = ref(false);
const saving = ref(false);
const cheques = ref([]);
const listMeta = ref({ total: 0, current_page: 1, per_page: 10, from: null, to: null, last_page: 1 });
const summary = ref([]);
const dueThisWeek = ref([]);
const createModal = ref(false);

const parties = ref([]);
const bankAccounts = ref([]);

const typeOptions = [
    { value: 'receivable', name: 'Receivable (from customer)' },
    { value: 'payable', name: 'Payable (to supplier)' },
];

const isReceivable = computed(() => form.value.type === 'receivable');
const partyLabel = computed(() => isReceivable.value ? 'Customer' : 'Supplier');
const depositDateLabel = computed(() => isReceivable.value ? 'Received / Deposit Date' : 'Issued Date');

const partyOptions = computed(() =>
    parties.value.map((p) => ({ id: p.id, name: p.phone ? `${p.name} — ${p.phone}` : p.name }))
);
const bankAccountOptions = computed(() =>
    bankAccounts.value.map((a) => ({ id: a.id, name: `${a.bank_name} — ${a.account_number}` }))
);

const statuses = [
    { value: 'pending', label: 'Pending' },
    { value: 'presented', label: 'Presented' },
    { value: 'cleared', label: 'Cleared' },
    { value: 'bounced', label: 'Bounced' },
    { value: 'cancelled', label: 'Cancelled' },
];

const statusLabel = (s) => statuses.find((x) => x.value === s)?.label ?? s;

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Cheque No', dataIndex: 'cheque_no', key: 'cheque_no' },
    { title: 'Party', key: 'party', dataIndex: ['party', 'name'] },
    { title: 'Bank', key: 'bank' },
    { title: 'Cheque Date', key: 'cheque_date' },
    { title: 'Amount', key: 'amount' },
    { title: 'Type', key: 'type' },
    { title: 'Status', key: 'status' },
    { title: 'Actions', key: 'action' },
];

const summaryCards = computed(() => {
    const groups = {};
    summary.value.forEach((r) => {
        groups[`${r.type}-${r.status}`] = r;
    });
    return [
        {
            label: 'Receivable Pending',
            count: groups['receivable-pending']?.count ?? 0,
            total: groups['receivable-pending']?.total ?? 0,
            icon: 'ti ti-arrow-down-circle',
            color: 'text-success',
        },
        {
            label: 'Payable Pending',
            count: groups['payable-pending']?.count ?? 0,
            total: groups['payable-pending']?.total ?? 0,
            icon: 'ti ti-arrow-up-circle',
            color: 'text-danger',
        },
        {
            label: 'Cleared Total',
            count: (groups['receivable-cleared']?.count ?? 0) + (groups['payable-cleared']?.count ?? 0),
            total: (groups['receivable-cleared']?.total ?? 0) - (groups['payable-cleared']?.total ?? 0),
            icon: 'ti ti-check-circle',
            color: 'text-primary',
        },
        {
            label: 'Bounced',
            count: (groups['receivable-bounced']?.count ?? 0) + (groups['payable-bounced']?.count ?? 0),
            total: (groups['receivable-bounced']?.total ?? 0) + (groups['payable-bounced']?.total ?? 0),
            icon: 'ti ti-alert-circle',
            color: 'text-warning',
        },
    ];
});

const form = ref({
    type: 'receivable',
    party_id: '',
    bank_account_id: '',
    cheque_no: '',
    bank_name: '',
    cheque_date: '',
    deposit_date: '',
    amount: '',
    remarks: '',
});

const actionModal = reactive({
    show: false,
    type: '',
    title: '',
    cheque: null,
    date: new Date().toISOString().slice(0, 10),
    remarks: '',
    saving: false,
});

function openActionModal(type, record) {
    const titles = { present: 'Mark as Presented', clear: 'Mark as Cleared', bounce: 'Mark as Bounced' };
    actionModal.type = type;
    actionModal.title = titles[type];
    actionModal.cheque = record;
    actionModal.date = new Date().toISOString().slice(0, 10);
    actionModal.remarks = '';
    actionModal.saving = false;
    actionModal.show = true;
}

async function submitAction() {
    actionModal.saving = true;
    try {
        if (actionModal.type === 'present') {
            await apiAdmin(`cheque/${actionModal.cheque.id}/present`, 'post', { deposit_date: actionModal.date });
            toast('success', 'Cheque marked as presented.');
        } else if (actionModal.type === 'clear') {
            await apiAdmin(`cheque/${actionModal.cheque.id}/clear`, 'post', { cleared_date: actionModal.date });
            toast('success', 'Cheque cleared.');
        } else if (actionModal.type === 'bounce') {
            await apiAdmin(`cheque/${actionModal.cheque.id}/bounce`, 'post', { remarks: actionModal.remarks });
            toast('success', 'Cheque marked as bounced.');
        }
        actionModal.show = false;
        fetchCheques();
        fetchSummary();
    } catch (e) {
        showErrors(e);
    } finally {
        actionModal.saving = false;
    }
}

const fetchCheques = async () => {
    loading.value = true;
    try {
        const { data } = await apiAdmin('cheque', 'get', {
            page: filter.page,
            per_page: filter.limit,
            ...(filter.type ? { type: filter.type } : {}),
            ...(filter.status ? { status: filter.status } : {}),
            ...(filter.search ? { search: filter.search } : {}),
        });
        cheques.value = data.data ?? [];
        const m = data.meta ?? data;
        listMeta.value = {
            total: m.total ?? 0,
            current_page: m.current_page ?? filter.page,
            per_page: m.per_page ?? filter.limit,
            from: m.from ?? null,
            to: m.to ?? null,
            last_page: m.last_page ?? 1,
        };
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', type: '', status: '', page: 1, limit: 10 },
    onFilter: fetchCheques,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => listMeta.value),
    filter,
});

function setFilter(key, value) {
    filter[key] = value;
}

watch(() => form.value.type, async () => {
    form.value.party_id = '';
    form.value.bank_name = '';
    form.value.bank_account_id = '';
    await fetchParties();
    if (!isReceivable.value && !bankAccounts.value.length) {
        await fetchBankAccounts();
    }
});

async function fetchParties() {
    try {
        const { data } = await apiAdmin('party', 'get', {
            per_page: 500,
            type: isReceivable.value ? 'customer' : 'supplier',
        });
        parties.value = data.data ?? [];
    } catch { /* optional */ }
}

async function fetchBankAccounts() {
    try {
        const { data } = await apiAdmin('bank-reconciliation/bank-accounts');
        bankAccounts.value = data.data ?? data ?? [];
    } catch { /* optional */ }
}

async function fetchSummary() {
    try {
        const { data } = await apiAdmin('cheque/summary');
        summary.value = data.data;
        dueThisWeek.value = data.due_this_week;
    } catch { /* optional */ }
}

function openCreate() {
    form.value = {
        type: 'receivable',
        party_id: '',
        bank_account_id: '',
        cheque_no: '',
        bank_name: '',
        cheque_date: '',
        deposit_date: '',
        amount: '',
        remarks: '',
    };
    createModal.value = true;
    fetchParties();
}

async function saveCheque() {
    saving.value = true;
    try {
        await apiAdmin('cheque', 'post', form.value);
        toast('success', 'Cheque recorded successfully.');
        createModal.value = false;
        fetchCheques();
        fetchSummary();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
}

async function cancelCheque(id) {
    try {
        await apiAdmin(`cheque/${id}/cancel`, 'post');
        toast('success', 'Cheque cancelled.');
        fetchCheques();
        fetchSummary();
    } catch (e) {
        showErrors(e);
    }
}

function statusClass(s) {
    return {
        pending: 'bg-warning text-dark',
        presented: 'bg-info',
        cleared: 'bg-success',
        bounced: 'bg-danger',
        cancelled: 'bg-secondary',
    }[s] ?? 'bg-secondary';
}

fetchSummary();
fetchBankAccounts();
</script>
