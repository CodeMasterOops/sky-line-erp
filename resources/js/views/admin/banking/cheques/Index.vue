<template>
    <PageHeader title="PDC Cheque Management" subtitle="Post-dated cheques — payable & receivable" @refresh="fetchCheques">
        <template #actions>
            <button v-can="'create_cheque'" type="button" class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Record Cheque
            </button>
        </template>
    </PageHeader>

    <!-- Summary Cards -->
    <section class="section" v-if="summary.length">
        <div class="row g-3 mb-3">
            <div class="col-md-3" v-for="card in summaryCards" :key="card.label">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div :class="['fs-2 me-3', card.color]"><i :class="card.icon"></i></div>
                            <div>
                                <div class="text-muted small">{{ card.label }}</div>
                                <div class="fw-bold">NPR {{ card.total.toLocaleString() }}</div>
                                <div class="small text-muted">{{ card.count }} cheques</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Due This Week -->
        <div v-if="dueThisWeek.length" class="alert alert-warning">
            <strong><i class="ti ti-alert-circle me-1"></i>{{ dueThisWeek.length }} cheque(s) due this week</strong>
            <span class="ms-2" v-for="c in dueThisWeek.slice(0,3)" :key="c.id">
                {{ c.cheque_no }} ({{ c.cheque_date }})
            </span>
        </div>
    </section>

    <section class="section">
        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" v-model="filter.type" @change="fetchCheques">
                            <option value="">All Types</option>
                            <option value="payable">Payable</option>
                            <option value="receivable">Receivable</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" v-model="filter.status" @change="fetchCheques">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="presented">Presented</option>
                            <option value="cleared">Cleared</option>
                            <option value="bounced">Bounced</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" v-model="filter.from_date" @change="fetchCheques" placeholder="From" />
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" v-model="filter.to_date" @change="fetchCheques" placeholder="To" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table :columns="columns" :data-source="cheques" :loading="loading" row-key="id">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'type'">
                                <span :class="record.type === 'receivable' ? 'badge bg-success' : 'badge bg-danger'">
                                    {{ record.type === 'receivable' ? 'Receivable' : 'Payable' }}
                                </span>
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="statusClass(record.status)" class="badge">{{ record.status }}</span>
                            </template>
                            <template v-if="column.key === 'amount'">
                                NPR {{ record.amount?.toLocaleString() }}
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button v-if="record.status === 'pending'" class="btn btn-xs btn-outline-warning"
                                        @click="presentCheque(record)">Present</button>
                                    <button v-if="['pending','presented'].includes(record.status)" class="btn btn-xs btn-outline-success"
                                        @click="clearCheque(record)">Clear</button>
                                    <button v-if="record.status === 'presented'" class="btn btn-xs btn-outline-danger"
                                        @click="bounceCheque(record)">Bounce</button>
                                    <button v-if="record.status !== 'cleared'" class="btn btn-xs btn-outline-secondary"
                                        @click="cancelCheque(record.id)">Cancel</button>
                                </div>
                            </template>
                        </template>
                    </a-table>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Cheque Modal -->
    <div v-if="createModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Record PDC Cheque</h5>
                    <button type="button" class="btn-close" @click="createModal = false"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select v-model="form.type" class="form-select">
                                <option value="receivable">Receivable (from customer)</option>
                                <option value="payable">Payable (to supplier)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Party</label>
                            <div class="position-relative">
                                <input
                                    v-model="partySearch"
                                    type="text"
                                    class="form-control"
                                    placeholder="Search party by name..."
                                    @input="onPartySearch"
                                    @focus="showPartyDropdown = true"
                                    @blur="hidePartyDropdown"
                                />
                                <div
                                    v-if="showPartyDropdown && filteredParties.length"
                                    class="position-absolute w-100 bg-white border rounded shadow-sm"
                                    style="z-index:1055;max-height:180px;overflow-y:auto;">
                                    <div
                                        v-for="p in filteredParties"
                                        :key="p.id"
                                        class="px-3 py-2 cursor-pointer hover-bg-light"
                                        style="cursor:pointer"
                                        @mousedown.prevent="selectParty(p)">
                                        <div class="fw-semibold small">{{ p.name }}</div>
                                        <div class="text-muted" style="font-size:0.75rem">{{ p.type }} · {{ p.phone }}</div>
                                    </div>
                                </div>
                                <div v-if="form.party_id" class="form-text text-success">
                                    <i class="ti ti-check me-1"></i>Selected: {{ partySearch }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cheque No <span class="text-danger">*</span></label>
                            <input v-model="form.cheque_no" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Name</label>
                            <input v-model="form.bank_name" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Branch</label>
                            <input v-model="form.bank_branch" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cheque Date <span class="text-danger">*</span></label>
                            <input v-model="form.cheque_date" type="date" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount (NPR) <span class="text-danger">*</span></label>
                            <input v-model="form.amount" type="number" class="form-control" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea v-model="form.remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="createModal = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="saving" @click="saveCheque">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save Cheque
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const saving = ref(false);
const cheques = ref([]);
const summary = ref([]);
const dueThisWeek = ref([]);
const createModal = ref(false);

// Party search state
const parties = ref([]);
const partySearch = ref('');
const showPartyDropdown = ref(false);
const filteredParties = computed(() => {
    const q = partySearch.value.trim().toLowerCase();
    if (!q) return parties.value.slice(0, 20);
    return parties.value.filter((p) => p.name.toLowerCase().includes(q)).slice(0, 20);
});

const onPartySearch = () => {
    showPartyDropdown.value = true;
};
const selectParty = (p) => {
    form.value.party_id = p.id;
    partySearch.value = p.name;
    showPartyDropdown.value = false;
};
const hidePartyDropdown = () => {
    setTimeout(() => { showPartyDropdown.value = false; }, 150);
};

const filter = ref({ type: '', status: '', from_date: '', to_date: '' });
const form = ref({ type: 'receivable', party_id: '', cheque_no: '', bank_name: '', bank_branch: '', cheque_date: '', amount: '', remarks: '' });

const columns = [
    { title: 'Cheque No', dataIndex: 'cheque_no', key: 'cheque_no' },
    { title: 'Party', key: 'party', dataIndex: ['party', 'name'] },
    { title: 'Bank', dataIndex: 'bank_name', key: 'bank_name' },
    { title: 'Cheque Date', dataIndex: 'cheque_date', key: 'cheque_date' },
    { title: 'Amount', key: 'amount' },
    { title: 'Type', key: 'type' },
    { title: 'Status', key: 'status' },
    { title: 'Actions', key: 'action' },
];

const summaryCards = computed(() => {
    const groups = {};
    summary.value.forEach(r => {
        const k = `${r.type}-${r.status}`;
        groups[k] = r;
    });
    return [
        { label: 'Receivable Pending', count: groups['receivable-pending']?.count ?? 0, total: groups['receivable-pending']?.total ?? 0, icon: 'ti ti-arrow-down-circle', color: 'text-success' },
        { label: 'Payable Pending', count: groups['payable-pending']?.count ?? 0, total: groups['payable-pending']?.total ?? 0, icon: 'ti ti-arrow-up-circle', color: 'text-danger' },
        { label: 'Cleared Total', count: (groups['receivable-cleared']?.count ?? 0) + (groups['payable-cleared']?.count ?? 0), total: (groups['receivable-cleared']?.total ?? 0) - (groups['payable-cleared']?.total ?? 0), icon: 'ti ti-check-circle', color: 'text-primary' },
        { label: 'Bounced', count: (groups['receivable-bounced']?.count ?? 0) + (groups['payable-bounced']?.count ?? 0), total: (groups['receivable-bounced']?.total ?? 0) + (groups['payable-bounced']?.total ?? 0), icon: 'ti ti-alert-circle', color: 'text-warning' },
    ];
});

onMounted(() => { fetchCheques(); fetchSummary(); fetchParties(); });

async function fetchParties() {
    try {
        const { data } = await apiAdmin('party', 'get', { per_page: 500 });
        parties.value = data.data ?? [];
    } catch { /* optional */ }
}

async function fetchCheques() {
    loading.value = true;
    try {
        const params = new URLSearchParams(Object.fromEntries(Object.entries(filter.value).filter(([,v]) => v)));
        const { data } = await apiAdmin(`cheque?${params}`);
        cheques.value = data.data;
    } finally { loading.value = false; }
}

async function fetchSummary() {
    const { data } = await apiAdmin('cheque/summary');
    summary.value = data.data;
    dueThisWeek.value = data.due_this_week;
}

function openCreate() {
    form.value = { type: 'receivable', party_id: '', cheque_no: '', bank_name: '', bank_branch: '', cheque_date: '', amount: '', remarks: '' };
    partySearch.value = '';
    showPartyDropdown.value = false;
    createModal.value = true;
}

async function saveCheque() {
    saving.value = true;
    try {
        await apiAdmin('cheque', 'post', form.value);
        toast('Cheque recorded successfully');
        createModal.value = false;
        fetchCheques();
        fetchSummary();
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function presentCheque(c) {
    const date = prompt('Deposit/Presentation date:', new Date().toISOString().slice(0,10));
    if (!date) return;
    await apiAdmin(`cheque/${c.id}/present`, 'post', { deposit_date: date });
    toast('Cheque marked as presented');
    fetchCheques(); fetchSummary();
}

async function clearCheque(c) {
    const date = prompt('Cleared date:', new Date().toISOString().slice(0,10));
    if (!date) return;
    await apiAdmin(`cheque/${c.id}/clear`, 'post', { cleared_date: date });
    toast('Cheque cleared');
    fetchCheques(); fetchSummary();
}

async function bounceCheque(c) {
    const remarks = prompt('Bounce reason:');
    await apiAdmin(`cheque/${c.id}/bounce`, 'post', { remarks });
    toast('Cheque marked as bounced');
    fetchCheques(); fetchSummary();
}

async function cancelCheque(id) {
    await apiAdmin(`cheque/${id}/cancel`, 'post');
    toast('Cheque cancelled');
    fetchCheques(); fetchSummary();
}

function statusClass(s) {
    return { pending: 'bg-warning text-dark', presented: 'bg-info', cleared: 'bg-success', bounced: 'bg-danger', cancelled: 'bg-secondary' }[s] ?? 'bg-secondary';
}
</script>
