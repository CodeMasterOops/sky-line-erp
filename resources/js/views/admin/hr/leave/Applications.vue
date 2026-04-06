<template>
    <PageHeader title="Leave Applications" subtitle="Manage employee leave requests">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> New Application
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select v-model="filters.status" @change="load" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="applications.loading" :colspan="8" />
                            <template v-else-if="applications.data.length">
                                <tr v-for="(app, i) in applications.data" :key="app.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ app.employee?.full_name }}</td>
                                    <td>{{ app.leave_type?.name }}</td>
                                    <td>{{ app.from_date }}</td>
                                    <td>{{ app.to_date }}</td>
                                    <td>{{ app.days }}</td>
                                    <td>
                                        <span :class="statusBadge(app.status)">{{ app.status_label }}</span>
                                    </td>
                                    <td style="width:150px;" class="text-center">
                                        <template v-if="app.status === 'pending' || app.status?.value === 'pending'">
                                            <button type="button" @click="approve(app.id)" class="btn btn-sm btn-outline-success me-1" title="Approve">
                                                <i class="ti ti-check"></i>
                                            </button>
                                            <button type="button" @click="reject(app.id)" class="btn btn-sm btn-outline-danger" title="Reject">
                                                <i class="ti ti-x"></i>
                                            </button>
                                        </template>
                                        <button type="button" @click="deleteApp(app.id)" class="btn btn-sm btn-outline-danger ms-1">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="8" class="text-center">No applications found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <CreateLeaveApplication v-model:create-modal-opened="createModalOpened" @created="load" />
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { useLeaveStore } from '@/stores/admin/hr/leave.js';
import CreateLeaveApplication from './CreateApplication.vue';

const store = useLeaveStore();
const { applications } = storeToRefs(store);
const createModalOpened = ref(false);
const filters = reactive({ status: 'pending' });

const load = () => store.getApplications(filters);
onMounted(load);

const statusBadge = (s) => ({ pending: 'badge bg-warning text-dark', approved: 'badge bg-success', rejected: 'badge bg-danger' }[s?.value ?? s] ?? 'badge bg-secondary');

const approve = async (id) => {
    try { const res = await store.approveApplication(id); toast(res.status, res.data.message); }
    catch (e) { showErrors(e); }
};

const reject = async (id) => {
    const { value: reason } = await Swal.fire({ title: 'Rejection Reason', input: 'textarea', showCancelButton: true });
    if (reason !== undefined) {
        try { const res = await store.rejectApplication(id, reason); toast(res.status, res.data.message); }
        catch (e) { showErrors(e); }
    }
};

const deleteApp = (id) => {
    Swal.fire({ title: 'Delete application?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => {
            if (r.value) {
                try { const res = await store.deleteApplication(id); toast(res.status, res.data.message); }
                catch (e) { showErrors(e); }
            }
        });
};
</script>
