<template>
    <PageHeader title="Recurring Journals" subtitle="Automate periodic journal entries">
        <template #actions>
            <button class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Recurring Journal
            </button>
        </template>
    </PageHeader>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Frequency</th>
                            <th>Next Run</th>
                            <th>End Date</th>
                            <th>Last Run</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
                        <tr v-else-if="!journals.length"><td colspan="7" class="text-center text-muted py-4">No recurring journals. Add one to automate periodic entries.</td></tr>
                        <tr v-for="journal in journals" :key="journal.id">
                            <td>{{ journal.name }}</td>
                            <td class="text-capitalize">{{ journal.frequency }}</td>
                            <td>{{ formatDate(journal.next_run_date) }}</td>
                            <td>{{ formatDate(journal.end_date) }}</td>
                            <td>{{ formatDate(journal.last_run_at) }}</td>
                            <td><span class="badge" :class="journal.is_active ? 'bg-success' : 'bg-secondary'">{{ journal.is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-success" @click="runNow(journal.id)" title="Run Now">
                                        <i class="ti ti-player-play"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" @click="editJournal(journal)"><i class="ti ti-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" @click="deleteJournal(journal.id)"><i class="ti ti-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="modal d-block" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingJournal ? 'Edit' : 'Create' }} Recurring Journal</h5>
                    <button class="btn-close" @click="showForm = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" v-model="form.name" placeholder="e.g. Monthly Rent" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Frequency *</label>
                            <select class="form-select" v-model="form.frequency">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Next Run *</label>
                            <input type="date" class="form-control" v-model="form.next_run_date" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" v-model="form.end_date" />
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Remarks</label>
                            <input type="text" class="form-control" v-model="form.remarks" />
                        </div>
                    </div>

                    <h6>Journal Lines</h6>
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50%">Account</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in form.items" :key="idx">
                                <td>
                                    <vue-select v-model="item.account_id" :options="accountOptions" :reduce="o => o.value" placeholder="Select account" />
                                </td>
                                <td><input type="number" class="form-control form-control-sm text-end" v-model="item.dr_amount" min="0" step="0.01" /></td>
                                <td><input type="number" class="form-control form-control-sm text-end" v-model="item.cr_amount" min="0" step="0.01" /></td>
                                <td>
                                    <button class="btn btn-sm btn-danger" @click="form.items.splice(idx, 1)"><i class="ti ti-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <button class="btn btn-sm btn-outline-primary" @click="form.items.push({account_id:null,dr_amount:0,cr_amount:0})">
                                        <i class="ti ti-plus me-1"></i> Add Line
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" @click="saveJournal" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import Swal from 'sweetalert2';

const journals = ref([]);
const loading = ref(false);
const showForm = ref(false);
const saving = ref(false);
const editingJournal = ref(null);
const accountOptions = ref([]);

const defaultForm = () => ({
    name: '', frequency: 'monthly', next_run_date: new Date().toISOString().split('T')[0],
    end_date: null, remarks: '',
    items: [
        { account_id: null, dr_amount: 0, cr_amount: 0 },
        { account_id: null, dr_amount: 0, cr_amount: 0 },
    ],
});

const form = ref(defaultForm());

const loadJournals = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('recurring-journal', 'get');
        journals.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const loadAccounts = async () => {
    try {
        const res = await apiAdmin('account', 'get', { per_page: 500 });
        accountOptions.value = (res.data.data || []).map(a => ({ label: `${a.name} (${a.code})`, value: a.id }));
    } catch (e) { /* ignore */ }
};

const openCreate = () => {
    editingJournal.value = null;
    form.value = defaultForm();
    showForm.value = true;
};

const editJournal = (journal) => {
    editingJournal.value = journal;
    form.value = {
        ...journal,
        items: journal.items || [{ account_id: null, dr_amount: 0, cr_amount: 0 }, { account_id: null, dr_amount: 0, cr_amount: 0 }],
    };
    showForm.value = true;
};

const saveJournal = async () => {
    saving.value = true;
    try {
        if (editingJournal.value) {
            await apiAdmin(`recurring-journal/${editingJournal.value.id}`, 'put', form.value);
            toast('success', 'Updated.');
        } else {
            await apiAdmin('recurring-journal', 'post', form.value);
            toast('success', 'Created.');
        }
        showForm.value = false;
        await loadJournals();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const runNow = async (id) => {
    try {
        const res = await apiAdmin(`recurring-journal/${id}/run-now`, 'post');
        toast('success', res.data.message);
        await loadJournals();
    } catch (e) { showErrors(e); }
};

const deleteJournal = async (id) => {
    const result = await Swal.fire({ title: 'Delete?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' });
    if (!result.value) return;
    try {
        await apiAdmin(`recurring-journal/${id}`, 'delete');
        toast('success', 'Deleted.');
        await loadJournals();
    } catch (e) { showErrors(e); }
};

onMounted(() => { loadJournals(); loadAccounts(); });
</script>
