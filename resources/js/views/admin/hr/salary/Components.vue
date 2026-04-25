<template>
    <PageHeader title="Salary Components" subtitle="Define earnings and deductions" @refresh="payrollStore.getComponents()">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Component
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Calculation</th>
                                <th>Taxable</th>
                                <th>GL Account</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="components.loading" :colspan="7" />
                            <template v-else-if="components.data.length">
                                <tr v-for="(c, i) in components.data" :key="c.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ c.name }}</td>
                                    <td><span :class="c.type_label === 'Earning' ? 'badge bg-success' : 'badge bg-danger'">{{ c.type_label }}</span></td>
                                    <td>{{ c.calculation_type }}</td>
                                    <td><span :class="c.is_taxable ? 'badge bg-warning text-dark' : 'badge bg-secondary'">{{ c.is_taxable ? 'Yes' : 'No' }}</span></td>
                                    <td class="small text-muted">{{ c.account_name || '—' }}</td>
                                    <td><span :class="c.is_active ? 'badge bg-success' : 'badge bg-secondary'">{{ c.is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td style="width:100px;">
                                        <button type="button" @click="editItem = { ...c }" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></button>
                                        <button type="button" @click="deleteComp(c.id)" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else><td colspan="8" class="text-center">No components found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <VModal :show-modal="createModalOpened" @close-click="createModalOpened = false" title="Add Salary Component">
        <template #modal-body>
            <form @submit.prevent="storeComp" class="row g-3">
                <div class="col-md-6"><VInput v-model="cForm.name" label="Name *" /></div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select v-model="cForm.type" class="form-select"><option value="earning">Earning</option><option value="deduction">Deduction</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Calculation</label>
                    <select v-model="cForm.calculation_type" class="form-select"><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">GL Account <span class="text-muted small">(for journal posting)</span></label>
                    <select v-model="cForm.account_id" class="form-select">
                        <option value="">-- Select Account --</option>
                        <option v-for="acc in accountList" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                </div>
                <div class="col-md-4 pt-3">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" v-model="cForm.is_taxable" /><label class="form-check-label">Taxable</label></div>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="createModalOpened = false" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <VModal :show-modal="!!editItem" @close-click="editItem = null" title="Edit Salary Component">
        <template #modal-body>
            <form v-if="editItem" @submit.prevent="updateComp" class="row g-3">
                <div class="col-md-6"><VInput v-model="editItem.name" label="Name *" /></div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select v-model="editItem.type" class="form-select"><option value="earning">Earning</option><option value="deduction">Deduction</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Calculation</label>
                    <select v-model="editItem.calculation_type" class="form-select"><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">GL Account <span class="text-muted small">(for journal posting)</span></label>
                    <select v-model="editItem.account_id" class="form-select">
                        <option value="">-- Select Account --</option>
                        <option v-for="acc in accountList" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                </div>
                <div class="col-md-4 pt-3">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" v-model="editItem.is_taxable" /><label class="form-check-label">Taxable</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" v-model="editItem.is_active" /><label class="form-check-label">Active</label></div>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="editItem = null" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="eSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';
import { apiAdmin } from '@/helpers/api.js';

const payrollStore = usePayrollStore();
const { components } = storeToRefs(payrollStore);
const createModalOpened = ref(false);
const editItem = ref(null);
const cSubmitting = ref(false);
const eSubmitting = ref(false);
const accountList = ref([]);
const cForm = reactive({ name: '', type: 'earning', calculation_type: 'fixed', is_taxable: false, account_id: '' });

onMounted(async () => {
    payrollStore.getComponents();
    try {
        const res = await apiAdmin('account?limit=200');
        accountList.value = res.data.data ?? [];
    } catch { /* ignore */ }
});

const storeComp = async () => {
    cSubmitting.value = true;
    try {
        const res = await payrollStore.storeComponent(cForm);
        toast(res.status, res.data.message);
        createModalOpened.value = false;
        Object.assign(cForm, { name: '', type: 'earning', calculation_type: 'fixed', is_taxable: false, account_id: '' });
    } catch (e) { showErrors(e); } finally { cSubmitting.value = false; }
};

const updateComp = async () => {
    eSubmitting.value = true;
    const payload = {
        name: editItem.value.name,
        type: editItem.value.type?.value ?? editItem.value.type,
        calculation_type: editItem.value.calculation_type,
        is_taxable: editItem.value.is_taxable,
        is_active: editItem.value.is_active,
        account_id: editItem.value.account_id || null,
    };
    try { const res = await payrollStore.updateComponent(editItem.value.id, payload); toast(res.status, res.data.message); editItem.value = null; }
    catch (e) { showErrors(e); } finally { eSubmitting.value = false; }
};

const deleteComp = (id) => {
    Swal.fire({ title: 'Delete component?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => { if (r.value) { try { const res = await payrollStore.deleteComponent(id); toast(res.status, res.data.message); } catch (e) { showErrors(e); } } });
};
</script>
