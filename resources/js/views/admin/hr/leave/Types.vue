<template>
    <PageHeader title="Leave Types" subtitle="Configure leave types" @refresh="leaveStore.getLeaveTypes()">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Type
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
                                <th>Days Allowed</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="leaveTypes.loading" :colspan="6" />
                            <template v-else-if="leaveTypes.data.length">
                                <tr v-for="(t, i) in leaveTypes.data" :key="t.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ t.name }}</td>
                                    <td>{{ t.days_allowed }}</td>
                                    <td><span :class="t.is_paid ? 'badge bg-success' : 'badge bg-secondary'">{{ t.is_paid ? 'Yes' : 'No' }}</span></td>
                                    <td><span :class="t.is_active ? 'badge bg-success' : 'badge bg-secondary'">{{ t.is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td style="width:100px;">
                                        <button type="button" @click="editItem = t" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></button>
                                        <button type="button" @click="deleteType(t.id)" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else><td colspan="6" class="text-center">No leave types found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Modal -->
    <VModal :show-modal="createModalOpened" @close-click="createModalOpened = false" title="Add Leave Type">
        <template #modal-body>
            <form @submit.prevent="storeType" class="row g-3">
                <div class="col-md-6"><VInput v-model="cForm.name" label="Name *" /></div>
                <div class="col-md-3"><VInput v-model="cForm.days_allowed" label="Days Allowed" type="number" /></div>
                <div class="col-md-3 pt-4">
                    <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" v-model="cForm.is_paid" /><label class="form-check-label">Paid</label></div>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="createModalOpened = false" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <!-- Edit Modal -->
    <VModal :show-modal="!!editItem" @close-click="editItem = null" title="Edit Leave Type">
        <template #modal-body>
            <form @submit.prevent="updateType" class="row g-3">
                <div class="col-md-6"><VInput v-model="editItem.name" label="Name *" /></div>
                <div class="col-md-3"><VInput v-model="editItem.days_allowed" label="Days Allowed" type="number" /></div>
                <div class="col-md-3 pt-4">
                    <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" v-model="editItem.is_paid" /><label class="form-check-label">Paid</label></div>
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
import { useLeaveStore } from '@/stores/admin/hr/leave.js';

const leaveStore = useLeaveStore();
const { leaveTypes } = storeToRefs(leaveStore);
const createModalOpened = ref(false);
const editItem = ref(null);
const cSubmitting = ref(false);
const eSubmitting = ref(false);
const cForm = reactive({ name: '', days_allowed: 0, is_paid: true });

onMounted(() => leaveStore.getLeaveTypes());

const storeType = async () => {
    cSubmitting.value = true;
    try { const res = await leaveStore.storeLeaveType(cForm); toast(res.status, res.data.message); createModalOpened.value = false; Object.assign(cForm, { name: '', days_allowed: 0, is_paid: true }); }
    catch (e) { showErrors(e); } finally { cSubmitting.value = false; }
};

const updateType = async () => {
    eSubmitting.value = true;
    try { const res = await leaveStore.updateLeaveType(editItem.value.id, editItem.value); toast(res.status, res.data.message); editItem.value = null; }
    catch (e) { showErrors(e); } finally { eSubmitting.value = false; }
};

const deleteType = (id) => {
    Swal.fire({ title: 'Delete?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => { if (r.value) { try { const res = await leaveStore.deleteLeaveType(id); toast(res.status, res.data.message); } catch (e) { showErrors(e); } } });
};
</script>
