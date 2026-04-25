<template>
    <PageHeader title="Currencies" subtitle="Manage global exchange rates (Base: NPR)">
        <template #actions>
            <button class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Currency
            </button>
        </template>
    </PageHeader>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Symbol</th>
                            <th class="text-end">Exchange Rate (to NPR)</th>
                            <th>Rate Date</th>
                            <th>Base</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
                        <tr v-else-if="!currencies.length"><td colspan="8" class="text-center text-muted py-4">No currencies. Add foreign currencies for multi-currency support.</td></tr>
                        <tr v-for="cur in currencies" :key="cur.id">
                            <td class="fw-semibold">{{ cur.code }}</td>
                            <td>{{ cur.name }}</td>
                            <td>{{ cur.symbol || '-' }}</td>
                            <td class="text-end">{{ cur.exchange_rate }}</td>
                            <td>{{ formatDate(cur.rate_date) }}</td>
                            <td>
                                <span v-if="cur.is_base" class="badge bg-primary">Base (NPR)</span>
                            </td>
                            <td>
                                <span class="badge" :class="cur.is_active ? 'bg-success' : 'bg-secondary'">
                                    {{ cur.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary" @click="editCurrency(cur)">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                    <button v-if="!cur.is_base" class="btn btn-sm btn-danger" @click="deleteCurrency(cur.id)">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div v-if="showForm" class="modal d-block" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingCurrency ? 'Edit' : 'Add' }} Currency</h5>
                    <button class="btn-close" @click="showForm = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" v-if="!editingCurrency">
                        <label class="form-label">Currency Code (3 chars) *</label>
                        <input type="text" class="form-control text-uppercase" v-model="form.code" maxlength="3" placeholder="USD, INR, EUR..." />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Currency Name *</label>
                        <input type="text" class="form-control" v-model="form.name" placeholder="US Dollar" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Symbol</label>
                        <input type="text" class="form-control" v-model="form.symbol" placeholder="$" maxlength="10" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Exchange Rate (1 unit = ? NPR) *</label>
                        <input type="number" class="form-control" v-model="form.exchange_rate" min="0.000001" step="0.0001" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rate Date</label>
                        <input type="date" class="form-control" v-model="form.rate_date" />
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="isActive" v-model="form.is_active" />
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" @click="saveCurrency" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiSuperAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import { formatDate } from '@/helpers/helper.js';
import Swal from 'sweetalert2';

const currencies = ref([]);
const loading = ref(false);
const showForm = ref(false);
const saving = ref(false);
const editingCurrency = ref(null);

const defaultForm = () => ({ code: '', name: '', symbol: '', exchange_rate: 1, rate_date: null, is_active: true });
const form = ref(defaultForm());

const loadCurrencies = async () => {
    loading.value = true;
    try {
        const res = await apiSuperAdmin('currency', 'get');
        currencies.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editingCurrency.value = null;
    form.value = defaultForm();
    showForm.value = true;
};

const editCurrency = (cur) => {
    editingCurrency.value = cur;
    form.value = { ...cur, rate_date: cur.rate_date ? cur.rate_date.slice(0, 10) : null };
    showForm.value = true;
};

const saveCurrency = async () => {
    saving.value = true;
    try {
        const payload = { ...form.value, code: form.value.code?.toUpperCase() };
        if (editingCurrency.value) {
            await apiSuperAdmin(`currency/${editingCurrency.value.id}`, 'put', payload);
            toast('success', 'Currency updated.');
        } else {
            await apiSuperAdmin('currency', 'post', payload);
            toast('success', 'Currency added.');
        }
        showForm.value = false;
        await loadCurrencies();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const deleteCurrency = async (id) => {
    const result = await Swal.fire({ title: 'Delete currency?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete' });
    if (!result.isConfirmed) return;
    try {
        await apiSuperAdmin(`currency/${id}`, 'delete');
        toast('success', 'Deleted.');
        await loadCurrencies();
    } catch (e) { showErrors(e); }
};

onMounted(() => { loadCurrencies(); });
</script>
