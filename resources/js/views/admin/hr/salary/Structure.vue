<template>
    <PageHeader title="Salary Structures" subtitle="Assign salary components to employees" @refresh="load">
        <template #actions>
            <button type="button" @click="showCreateModal = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Assign Structure
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
                                <th>Employee</th>
                                <th>Effective From</th>
                                <th>Gross</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="structures.loading" :colspan="6" />
                            <template v-else-if="structures.data.length">
                                <tr v-for="(s, i) in structures.data" :key="s.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ s.employee?.full_name }}</td>
                                    <td>{{ s.effective_from }}</td>
                                    <td>{{ grossOf(s) }}</td>
                                    <td><span :class="s.is_active ? 'badge bg-success' : 'badge bg-secondary'">{{ s.is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td style="width:100px;">
                                        <button type="button" @click="viewStructure(s)" class="btn btn-sm btn-outline-info me-1"><i class="ti ti-eye"></i></button>
                                        <button type="button" @click="deleteStructure(s.id)" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else><td colspan="6" class="text-center">No salary structures found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Structure Modal -->
    <VModal :show-modal="showCreateModal" @close-click="showCreateModal = false" title="Assign Salary Structure" size="lg">
        <template #modal-body>
            <form @submit.prevent="storeStructure" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Employee *</label>
                    <select v-model="cForm.employee_id" class="form-select">
                        <option value="">Select Employee</option>
                        <option v-for="e in employees.data" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <VInput v-model="cForm.effective_from" label="Effective From *" type="date" />
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Salary Components</h6>
                        <button type="button" @click="addItem" class="btn btn-sm btn-outline-primary"><i class="ti ti-plus"></i> Add</button>
                    </div>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Component</th><th>Amount</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="(item, idx) in cForm.items" :key="idx">
                                <td>
                                    <select v-model="item.salary_component_id" class="form-select form-select-sm">
                                        <option value="">Select</option>
                                        <option v-for="c in components.data" :key="c.id" :value="c.id">{{ c.name }} ({{ c.type_label }})</option>
                                    </select>
                                </td>
                                <td><input type="number" v-model="item.amount" class="form-control form-control-sm" step="0.01" /></td>
                                <td><button type="button" @click="cForm.items.splice(idx, 1)" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="showCreateModal = false" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <!-- View Structure Modal -->
    <VModal :show-modal="!!viewItem" @close-click="viewItem = null" title="Salary Structure Details">
        <template #modal-body>
            <div v-if="viewItem">
                <p><strong>Employee:</strong> {{ viewItem.employee?.full_name }}</p>
                <p><strong>Effective From:</strong> {{ viewItem.effective_from }}</p>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Component</th><th>Type</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr v-for="item in viewItem.items" :key="item.id">
                            <td>{{ item.component?.name }}</td>
                            <td>{{ item.component?.type_label }}</td>
                            <td class="text-end">{{ item.amount }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';

const payrollStore = usePayrollStore();
const empStore = useEmployeeStore();
const { structures, components } = storeToRefs(payrollStore);
const { employees } = storeToRefs(empStore);

const showCreateModal = ref(false);
const viewItem = ref(null);
const cSubmitting = ref(false);
const cForm = reactive({ employee_id: '', effective_from: '', items: [{ salary_component_id: '', amount: 0 }] });

const load = () => {
    payrollStore.getStructures();
    payrollStore.getComponents();
    empStore.getEmployees({ limit: 200 });
};
onMounted(load);

const addItem = () => cForm.items.push({ salary_component_id: '', amount: 0 });
const grossOf = (s) => s.items?.filter(i => i.component?.type_label === 'Earning').reduce((sum, i) => sum + i.amount, 0) ?? '—';
const viewStructure = (s) => { viewItem.value = s; };

const storeStructure = async () => {
    cSubmitting.value = true;
    try {
        const res = await payrollStore.storeStructure(cForm);
        toast(res.status, res.data.message);
        showCreateModal.value = false;
        Object.assign(cForm, { employee_id: '', effective_from: '', items: [{ salary_component_id: '', amount: 0 }] });
    } catch (e) { showErrors(e); }
    finally { cSubmitting.value = false; }
};

const deleteStructure = (id) => {
    Swal.fire({ title: 'Delete structure?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => { if (r.value) { try { const res = await payrollStore.deleteStructure(id); toast(res.status, res.data.message); } catch (e) { showErrors(e); } } });
};
</script>
