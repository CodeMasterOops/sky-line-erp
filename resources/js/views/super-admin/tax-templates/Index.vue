<template>
    <PageHeader title="Tax Templates" subtitle="Default tax rates seeded to new companies on registration">
        <template #actions>
            <button class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Template
            </button>
        </template>
    </PageHeader>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
                <i class="ti ti-info-circle fs-5 mt-1"></i>
                <div>
                    <strong>How this works:</strong> These templates are applied as system tax rates when a new company registers.
                    Company admins can view system taxes but cannot edit or delete them.
                    You can add, edit, or remove templates here — changes apply to <em>new</em> companies only.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th class="text-end">Rate (%)</th>
                            <th>Type</th>
                            <th>TDS Category</th>
                            <th>Seeded by Default</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
                        <tr v-else-if="!templates.length"><td colspan="7" class="text-center text-muted py-4">No templates. Add Nepal-standard VAT and TDS defaults.</td></tr>
                        <tr v-for="tmpl in templates" :key="tmpl.id">
                            <td class="fw-semibold">{{ tmpl.name }}</td>
                            <td class="text-end">{{ tmpl.rate }}%</td>
                            <td>{{ tmpl.type_label || '–' }}</td>
                            <td>{{ tmpl.tds_category_label || '–' }}</td>
                            <td>
                                <span class="badge" :class="tmpl.is_default ? 'bg-success' : 'bg-secondary'">
                                    {{ tmpl.is_default ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ tmpl.description || '–' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary" @click="editTemplate(tmpl)"><i class="ti ti-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" @click="deleteTemplate(tmpl.id)"><i class="ti ti-trash"></i></button>
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
                    <h5 class="modal-title">{{ editingTemplate ? 'Edit' : 'Add' }} Tax Template</h5>
                    <button class="btn-close" @click="showForm = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" v-model="form.name" placeholder="e.g. VAT 13%, TDS on Rent" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rate (%) *</label>
                        <input type="number" class="form-control" v-model="form.rate" min="0" max="100" step="0.01" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" v-model="form.type">
                            <option value="">– None –</option>
                            <option value="vat_standard">VAT Standard (13%)</option>
                            <option value="vat_exempt">VAT Exempt</option>
                            <option value="vat_zero_rated">VAT Zero Rated</option>
                            <option value="tds">TDS</option>
                        </select>
                    </div>
                    <div class="mb-3" v-if="form.type === 'tds'">
                        <label class="form-label">TDS Category</label>
                        <select class="form-select" v-model="form.tds_category">
                            <option value="">– None –</option>
                            <option value="service_vat_bill">Service (VAT Bill) – 1.5%</option>
                            <option value="service_pan_bill">Service (PAN Bill) – 15%</option>
                            <option value="service_exempt">Service (Exempt Institution) – 1%</option>
                            <option value="contract">Contract (VAT Registered) – 1.5%</option>
                            <option value="rent_property">Rent (Property) – 10%</option>
                            <option value="rent_vehicle_vat">Rent (Vehicle, VAT Bill) – 1.5%</option>
                            <option value="dividend">Dividend – 5%</option>
                            <option value="interest">Interest – 6%</option>
                            <option value="royalty">Royalty / Commission – 15%</option>
                            <option value="windfall">Windfall Gains – 25%</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" v-model="form.description" rows="2"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="isDefault" v-model="form.is_default" />
                        <label class="form-check-label" for="isDefault">Seed to new companies by default</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" @click="saveTemplate" :disabled="saving">
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
import Swal from 'sweetalert2';

const templates = ref([]);
const loading = ref(false);
const showForm = ref(false);
const saving = ref(false);
const editingTemplate = ref(null);

const defaultForm = () => ({ name: '', rate: 0, type: '', tds_category: '', is_default: true, description: '' });
const form = ref(defaultForm());

const loadTemplates = async () => {
    loading.value = true;
    try {
        const res = await apiSuperAdmin('tax-template', 'get');
        templates.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editingTemplate.value = null;
    form.value = defaultForm();
    showForm.value = true;
};

const editTemplate = (tmpl) => {
    editingTemplate.value = tmpl;
    form.value = { ...tmpl };
    showForm.value = true;
};

const saveTemplate = async () => {
    saving.value = true;
    try {
        const payload = { ...form.value, type: form.value.type || null, tds_category: form.value.tds_category || null };
        if (editingTemplate.value) {
            await apiSuperAdmin(`tax-template/${editingTemplate.value.id}`, 'put', payload);
            toast('success', 'Template updated.');
        } else {
            await apiSuperAdmin('tax-template', 'post', payload);
            toast('success', 'Template created.');
        }
        showForm.value = false;
        await loadTemplates();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const deleteTemplate = async (id) => {
    const result = await Swal.fire({ title: 'Delete template?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete' });
    if (!result.isConfirmed) return;
    try {
        await apiSuperAdmin(`tax-template/${id}`, 'delete');
        toast('success', 'Deleted.');
        await loadTemplates();
    } catch (e) { showErrors(e); }
};

onMounted(() => { loadTemplates(); });
</script>
